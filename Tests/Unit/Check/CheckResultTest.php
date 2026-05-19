<?php

declare(strict_types=1);

namespace Maispace\MaiAccessibility\Tests\Unit\Check;

use Maispace\MaiAccessibility\Check\CheckResult;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CheckResultTest extends TestCase
{
    // ── Static factory methods ──────────────────────────────────────────────

    #[Test]
    public function errorFactorySetsSeverityToError(): void
    {
        $result = CheckResult::error('check_id', 'Something is wrong');
        self::assertSame('error', $result->severity);
    }

    #[Test]
    public function warningFactorySetsSeverityToWarning(): void
    {
        $result = CheckResult::warning('check_id', 'Heads up');
        self::assertSame('warning', $result->severity);
    }

    #[Test]
    public function infoFactorySetsSeverityToInfo(): void
    {
        $result = CheckResult::info('check_id', 'FYI');
        self::assertSame('info', $result->severity);
    }

    #[Test]
    public function errorFactoryAssignsCheckIdentifier(): void
    {
        $result = CheckResult::error('my_check', 'msg');
        self::assertSame('my_check', $result->checkIdentifier);
    }

    #[Test]
    public function errorFactoryAssignsMessage(): void
    {
        $result = CheckResult::error('id', 'The error message');
        self::assertSame('The error message', $result->message);
    }

    #[Test]
    public function errorFactoryAssignsContext(): void
    {
        $result = CheckResult::error('id', 'msg', '<img src="x.jpg">');
        self::assertSame('<img src="x.jpg">', $result->context);
    }

    #[Test]
    public function errorFactoryAssignsPageUid(): void
    {
        $result = CheckResult::error('id', 'msg', '', 42);
        self::assertSame(42, $result->pageUid);
    }

    #[Test]
    public function contextDefaultsToEmptyString(): void
    {
        $result = CheckResult::error('id', 'msg');
        self::assertSame('', $result->context);
    }

    #[Test]
    public function pageUidDefaultsToZero(): void
    {
        $result = CheckResult::warning('id', 'msg');
        self::assertSame(0, $result->pageUid);
    }

    // ── isError / isWarning ─────────────────────────────────────────────────

    #[Test]
    public function isErrorReturnsTrueForError(): void
    {
        $result = CheckResult::error('id', 'msg');
        self::assertTrue($result->isError());
    }

    #[Test]
    public function isErrorReturnsFalseForWarning(): void
    {
        $result = CheckResult::warning('id', 'msg');
        self::assertFalse($result->isError());
    }

    #[Test]
    public function isErrorReturnsFalseForInfo(): void
    {
        $result = CheckResult::info('id', 'msg');
        self::assertFalse($result->isError());
    }

    #[Test]
    public function isWarningReturnsTrueForWarning(): void
    {
        $result = CheckResult::warning('id', 'msg');
        self::assertTrue($result->isWarning());
    }

    #[Test]
    public function isWarningReturnsFalseForError(): void
    {
        $result = CheckResult::error('id', 'msg');
        self::assertFalse($result->isWarning());
    }

    #[Test]
    public function isWarningReturnsFalseForInfo(): void
    {
        $result = CheckResult::info('id', 'msg');
        self::assertFalse($result->isWarning());
    }

    // ── Constructor ─────────────────────────────────────────────────────────

    #[Test]
    public function constructorAssignsAllProperties(): void
    {
        $result = new CheckResult('my_check', 'warning', 'A warning', 'some context', 7);
        self::assertSame('my_check', $result->checkIdentifier);
        self::assertSame('warning', $result->severity);
        self::assertSame('A warning', $result->message);
        self::assertSame('some context', $result->context);
        self::assertSame(7, $result->pageUid);
    }
}
