<?php

declare(strict_types=1);

namespace TalkToExcel;

use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\IReader;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

final class ExcelParser
{
   // private const ALLOWED_EXTENSIONS = ['xlsx', 'xls', 'csv'];
    //   private const ALLOWED_READERS = [IOFactory::READER_XLSX, IOFactory::READER_XLS, IOFactory::READER_CSV];

      private const ALLOWED_EXTENSIONS = ['csv'];
      private const ALLOWED_READERS = [IOFactory::READER_CSV];

    /**
     * @return array{
     *   context:string,
     *   metadata:array{row_count:int,sheet_count:int,context_bytes:int,is_truncated:bool},
     *   extension:string
     * }
     */
    public function parseUploadedFile(array $file): array
    {
        $this->validateUploadArray($file);

        $tmpPath = (string) $file['tmp_name'];
        $originalName = Security::cleanFilename((string) $file['name']);
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        if (!in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
          throw new \DomainException('Only CSV files are allowed.');
        }

        $maxUploadBytes = Env::int('MAX_UPLOAD_BYTES', 10 * 1024 * 1024);
        if ((int) $file['size'] > $maxUploadBytes) {
            throw new \DomainException('The uploaded file is larger than the configured limit.');
        }

        $readerType = IOFactory::identify($tmpPath, self::ALLOWED_READERS);
        // $expectedReader = match ($extension) {
        //     'xlsx' => IOFactory::READER_XLSX,
        //     'xls' => IOFactory::READER_XLS,
        //     'csv' => IOFactory::READER_CSV,
        // };
        $expectedReader = IOFactory::READER_CSV;

        if ($readerType !== $expectedReader) {
            throw new \DomainException('The file content does not match its extension.');
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($tmpPath) ?: 'application/octet-stream';
        $this->validateMime($extension, $mime);

        $maxRows = max(1, Env::int('MAX_ROWS', 5000));
        $maxColumns = max(1, Env::int('MAX_COLUMNS', 100));
        $maxCellChars = max(1, Env::int('MAX_CELL_CHARS', 500));
        $maxContextBytes = max(10000, Env::int('MAX_CONTEXT_BYTES', 1_000_000));

        $metadataReader = $this->makeReader($readerType, $tmpPath);
        $worksheetInfo = $metadataReader->listWorksheetInfo($tmpPath);

        $lines = [
            'WORKBOOK DATA',
            'Original file: ' . $originalName,
            'Rows are represented as: row_number<TAB>cell_1<TAB>cell_2...',
            'Blank cells are preserved between tab separators.',
        ];
        $contextBytes = strlen(implode("\n", $lines));

        $processedPhysicalRows = 0;
        $nonEmptyRows = 0;
        $loadedSheets = 0;
        $truncated = false;

        foreach ($worksheetInfo as $sheetInfo) {
            if ($processedPhysicalRows >= $maxRows || $truncated) {
                break;
            }

            $sheetName = (string) ($sheetInfo['worksheetName'] ?? 'Sheet');
            $sheetRows = max(0, (int) ($sheetInfo['totalRows'] ?? 0));
            if ($sheetRows === 0) {
                continue;
            }

            $rowsToRead = min($sheetRows, $maxRows - $processedPhysicalRows);
            $reader = $this->makeReader($readerType, $tmpPath);
            $reader->setLoadSheetsOnly([$sheetName]);
            $reader->setReadFilter(new RowRangeReadFilter(1, $rowsToRead));

            $spreadsheet = $reader->load($tmpPath);
            $sheet = $spreadsheet->getSheetByName($sheetName);
            if ($sheet === null) {
                $spreadsheet->disconnectWorksheets();
                unset($spreadsheet);
                continue;
            }

            $sheetMarker = "\n### SHEET: " . $this->sanitizeCell($sheetName, 120);
            if (!$this->appendWithinLimit($lines, $sheetMarker, $maxContextBytes, $contextBytes)) {
                $truncated = true;
                $spreadsheet->disconnectWorksheets();
                unset($spreadsheet);
                break;
            }

            $loadedSheets++;
            $highestColumnIndex = min(
                $maxColumns,
                Coordinate::columnIndexFromString($sheet->getHighestDataColumn())
            );

            for ($row = 1; $row <= $rowsToRead; $row++) {
                $processedPhysicalRows++;
                $values = [];
                $hasValue = false;

                for ($column = 1; $column <= $highestColumnIndex; $column++) {
                    $cell = $sheet->getCell([$column, $row]);
                    $value = $this->displayCellValue($cell);

                    if (is_bool($value)) {
                        $value = $value ? 'TRUE' : 'FALSE';
                    } elseif ($value === null) {
                        $value = '';
                    } elseif (is_array($value) || is_object($value)) {
                        $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
                    }

                    $clean = $this->sanitizeCell((string) $value, $maxCellChars);
                    $values[] = $clean;
                    $hasValue = $hasValue || $clean !== '';
                }

                if (!$hasValue) {
                    continue;
                }

                while ($values !== [] && end($values) === '') {
                    array_pop($values);
                }

                $line = $row . "\t" . implode("\t", $values);
                if (!$this->appendWithinLimit($lines, $line, $maxContextBytes, $contextBytes)) {
                    $truncated = true;
                    break;
                }

                $nonEmptyRows++;
            }

            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);
        }

        if ($processedPhysicalRows >= $maxRows) {
            $remainingRows = array_sum(array_map(
                static fn (array $info): int => (int) ($info['totalRows'] ?? 0),
                $worksheetInfo
            )) > $maxRows;
            $truncated = $truncated || $remainingRows;
        }

        $context = implode("\n", $lines);

        return [
            'context' => $context,
            'metadata' => [
                'row_count' => $nonEmptyRows,
                'sheet_count' => $loadedSheets,
                'context_bytes' => strlen($context),
                'is_truncated' => $truncated,
            ],
            'extension' => $extension,
        ];
    }

    private function makeReader(string $readerType, string $path): IReader
    {
        $reader = IOFactory::createReader($readerType);
        // Keep styles so Excel date/time serials can be converted to their displayed values.
        $reader->setReadDataOnly(false);
        $reader->setReadEmptyCells(false);
        if (method_exists($reader, 'setAllowExternalImages')) {
            $reader->setAllowExternalImages(false);
        }

        if ($readerType === IOFactory::READER_CSV && method_exists($reader, 'setInputEncoding')) {
            $reader->setInputEncoding('UTF-8');
        }

        return $reader;
    }

    private function validateUploadArray(array $file): void
    {
        foreach (['name', 'tmp_name', 'size', 'error'] as $key) {
            if (!array_key_exists($key, $file)) {
                throw new \DomainException('The upload payload is incomplete.');
            }
        }

        if ((int) $file['error'] !== UPLOAD_ERR_OK) {
            throw new \DomainException($this->uploadErrorMessage((int) $file['error']));
        }

        if (!is_uploaded_file((string) $file['tmp_name'])) {
            throw new \DomainException('The file was not received as a valid HTTP upload.');
        }

        if ((int) $file['size'] <= 0) {
            throw new \DomainException('The uploaded file is empty.');
        }
    }

    private function validateMime(string $extension, string $mime): void
    {
        $allowed = [
            'xlsx' => [
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'application/zip',
                'application/octet-stream',
            ],
            'xls' => [
                'application/vnd.ms-excel',
                'application/x-ole-storage',
                'application/octet-stream',
            ],
            'csv' => [
                'text/csv',
                'text/plain',
                'application/csv',
                'application/vnd.ms-excel',
                'application/octet-stream',
            ],
        ];

        if (!in_array($mime, $allowed[$extension], true)) {
            throw new \DomainException('The uploaded file has an unsupported MIME type.');
        }
    }

    private function displayCellValue(Cell $cell): mixed
    {
        if ($cell->getDataType() === DataType::TYPE_FORMULA) {
            // Use the workbook's cached formula result instead of evaluating untrusted formulas on the server.
            try {
                $cached = $cell->getOldCalculatedValue();
                if ($cached !== null) {
                    $formatCode = $cell->getStyle()->getNumberFormat()->getFormatCode();
                    return NumberFormat::toFormattedString($cached, $formatCode);
                }
            } catch (\Throwable) {
                // Fall back to the literal formula below.
            }

            return (string) $cell->getValue();
        }

        try {
            return $cell->getFormattedValue();
        } catch (\Throwable) {
            return $cell->getValue();
        }
    }

    private function sanitizeCell(string $value, int $maxChars): string
    {
        $value = str_replace(["\r\n", "\r", "\n", "\t"], [' ', ' ', ' ', ' '], $value);
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? '';
        $value = trim(preg_replace('/\s{2,}/u', ' ', $value) ?? $value);
        return mb_substr($value, 0, $maxChars);
    }

    /** @param list<string> $lines */
    private function appendWithinLimit(array &$lines, string $line, int $maxBytes, int &$currentBytes): bool
    {
        $requiredBytes = 1 + strlen($line);
        if ($currentBytes + $requiredBytes > $maxBytes) {
            return false;
        }

        $lines[] = $line;
        $currentBytes += $requiredBytes;
        return true;
    }

    private function uploadErrorMessage(int $code): string
    {
        return match ($code) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'The uploaded file is too large.',
            UPLOAD_ERR_PARTIAL => 'The file upload was interrupted.',
            UPLOAD_ERR_NO_FILE => 'Choose an Excel file to upload.',
            UPLOAD_ERR_NO_TMP_DIR => 'The server upload directory is unavailable.',
            UPLOAD_ERR_CANT_WRITE => 'The server could not save the uploaded file.',
            UPLOAD_ERR_EXTENSION => 'A server extension stopped the upload.',
            default => 'The file upload failed.',
        };
    }
}
