<?php

declare(strict_types=1);

namespace Maispace\MaiAccessibility\Tests\Unit\Check;

use Maispace\MaiAccessibility\Check\AriaAttributeCheck;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class AriaAttributeCheckTest extends TestCase
{
    private AriaAttributeCheck $subject;

    protected function setUp(): void
    {
        $this->subject = new AriaAttributeCheck();
    }

    #[Test]
    public function identifierIsAriaAttributes(): void
    {
        self::assertSame('aria_attributes', $this->subject->getIdentifier());
    }

    #[Test]
    public function emptyHtmlReturnsNoResults(): void
    {
        self::assertSame([], $this->subject->check('', 1));
    }

    #[Test]
    public function validRoleProducesNoResult(): void
    {
        $html = '<nav role="navigation"><a href="/">Home</a></nav>';
        self::assertSame([], $this->subject->check($html, 1));
    }

    #[Test]
    public function unknownRoleProducesError(): void
    {
        $html = '<div role="foobar">Content</div>';
        $results = $this->subject->check($html, 1);
        self::assertCount(1, $results);
        self::assertSame('error', $results[0]->severity);
        self::assertSame('aria_attributes', $results[0]->checkIdentifier);
        self::assertStringContainsString('foobar', $results[0]->message);
    }

    #[Test]
    public function ariaHiddenWithTabindexProducesWarning(): void
    {
        $html = '<div aria-hidden="true" tabindex="0">Hidden but focusable</div>';
        $results = $this->subject->check($html, 1);
        self::assertCount(1, $results);
        self::assertSame('warning', $results[0]->severity);
    }

    #[Test]
    public function ariaHiddenWithoutTabindexProducesNoResult(): void
    {
        $html = '<div aria-hidden="true">Decorative</div>';
        self::assertSame([], $this->subject->check($html, 1));
    }

    #[Test]
    public function ariaLabelledByReferencingMissingIdProducesError(): void
    {
        $html = '<button aria-labelledby="nonexistent-id">Click</button>';
        $results = $this->subject->check($html, 1);
        self::assertCount(1, $results);
        self::assertSame('error', $results[0]->severity);
        self::assertStringContainsString('nonexistent-id', $results[0]->message);
    }

    #[Test]
    public function ariaLabelledByReferencingPresentIdProducesNoResult(): void
    {
        $html = '<span id="label-text">Submit form</span><button aria-labelledby="label-text">Submit</button>';
        self::assertSame([], $this->subject->check($html, 1));
    }

    #[Test]
    public function pageUidIsAssignedToResult(): void
    {
        $html = '<div role="invalid">Content</div>';
        $results = $this->subject->check($html, 99);
        self::assertSame(99, $results[0]->pageUid);
    }
}
