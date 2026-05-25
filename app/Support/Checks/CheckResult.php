<?php

namespace App\Support\Checks;

/**
 * Outcome of a single rule applied to a parsed report PDF.
 */
class CheckResult
{
    public const PASS = 'pass';

    public const WARN = 'warn';

    public const FAIL = 'fail';

    public function __construct(
        public string $label,
        public string $status,
        public string $detail = '',
    ) {}

    public static function pass(string $label, string $detail = ''): self
    {
        return new self($label, self::PASS, $detail);
    }

    public static function warn(string $label, string $detail = ''): self
    {
        return new self($label, self::WARN, $detail);
    }

    public static function fail(string $label, string $detail = ''): self
    {
        return new self($label, self::FAIL, $detail);
    }
}
