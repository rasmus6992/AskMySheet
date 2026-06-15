<?php

declare(strict_types=1);

namespace TalkToExcel;

final class OpenAIException extends \RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $httpStatus,
        public readonly string $errorCode
    ) {
        parent::__construct($message);
    }
}
