<?php

declare(strict_types=1);

namespace TalkToExcel;

use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;

final class RowRangeReadFilter implements IReadFilter
{
    public function __construct(
        private readonly int $startRow,
        private readonly int $endRow
    ) {
    }

    public function readCell(string $columnAddress, int $row, string $worksheetName = ''): bool
    {
        return $row >= $this->startRow && $row <= $this->endRow;
    }
}
