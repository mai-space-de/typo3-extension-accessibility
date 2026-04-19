<?php

declare(strict_types=1);

namespace Maispace\MaiAccessibility\Tests\Unit\Check;

use Maispace\MaiAccessibility\Check\LinkTextCheck;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class LinkTextCheckTest extends TestCase
{
    private LinkTextCheck $subject;

    protected function setUp(): void
    {
        $this->subject = new LinkTextCheck();
    }

    #[Test]
    public function identifierIsLinkText(): void
    {
        self::assertSame('link_text', $this->subject->getIdentifier());
    }

    #[Test]
    public function emptyHtmlReturnsNoResults(): void
    {
        self::assertSame([], $this->subject->check('', 1));
    }

    #[Test]
    public function descriptiveLinkTextProducesNoResults(): void
    {
        $html = '<a href="/about">Learn more about our services</a>';
        self::assertSame([], $this->subject->check($html, 1));
    }

    #[Test]
    public function nonDescriptiveLinkTextProducesWarning(): void
    {
        $html = '<a href="/about">click here</a>';
        $results = $this->subject->check($html, 1);
        self::assertCount(1, $results);
        self::assertSame('warning', $results[0]->severity);
    }

    #[Test]
    public function emptyLinkTextProducesError(): void
    {
        $html = '<a href="/about"></a>';
        $results = $this->subject->check($html, 1);
        self::assertCount(1, $results);
        self::assertSame('error', $results[0]->severity);
    }

    #[Test]
    public function linkWithAriaLabelIsSkipped(): void
    {
        $html = '<a href="/about" aria-label="Read about our services">click here</a>';
        self::assertSame([], $this->subject->check($html, 1));
    }

    #[Test]
    public function anchorLinksAreSkipped(): void
    {
        $html = '<a href="#section">click here</a>';
        self::assertSame([], $this->subject->check($html, 1));
    }
}
