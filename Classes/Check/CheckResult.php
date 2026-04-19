<?php

declare(strict_types=1);

namespace Maispace\MaiAccessibility\Check;

final class CheckResult
{
    public function __construct(
        public readonly string $checkIdentifier,
        public readonly string $severity,
        public readonly string $message,
        public readonly string $context = '',
        public readonly int $pageUid = 0,
    ) {}

    public static function error(string $checkIdentifier, string $message, string $context = '', int $pageUid = 0): self
    {
        return new self($checkIdentifier, 'error', $message, $context, $pageUid);
    }

    public static function warning(string $checkIdentifier, string $message, string $context = '', int $pageUid = 0): self
    {
        return new self($checkIdentifier, 'warning', $message, $context, $pageUid);
    }

    public static function info(string $checkIdentifier, string $message, string $context = '', int $pageUid = 0): self
    {
        return new self($checkIdentifier, 'info', $message, $context, $pageUid);
    }

    public function isError(): bool
    {
        return $this->severity === 'error';
    }

    public function isWarning(): bool
    {
        return $this->severity === 'warning';
    }
}
