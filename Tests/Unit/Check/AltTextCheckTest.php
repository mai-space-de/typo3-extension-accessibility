<?php

declare(strict_types=1);

namespace Maispace\MaiAccessibility\Tests\Unit\Check;

use Maispace\MaiAccessibility\Check\AltTextCheck;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class AltTextCheckTest extends TestCase
{
    private AltTextCheck $subject;

    protected function setUp(): void
    {
        $this->subject = new AltTextCheck();
    }

    #[Test]
    public function identifierIsAltText(): void
    {
        self::assertSame('alt_text', $this->subject->getIdentifier());
    }

    #[Test]
    public function emptyHtmlReturnsNoResults(): void
    {
        $results = $this->subject->check('', 1);
        self::assertSame([], $results);
    }

    #[Test]
    public function imageWithAltTextProducesNoResults(): void
    {
        $html = '<img src="test.jpg" alt="A descriptive text">';
        $results = $this->subject->check($html, 1);
        self::assertSame([], $results);
    }

    #[Test]
    public function imageWithEmptyAltProducesWarning(): void
    {
        $html = '<img src="decorative.jpg" alt="">';
        $results = $this->subject->check($html, 1);
        self::assertCount(1, $results);
        self::assertSame('warning', $results[0]->severity);
        self::assertSame('alt_text', $results[0]->checkIdentifier);
    }

    #[Test]
    public function imageMissingAltAttributeProducesError(): void
    {
        $html = '<img src="photo.jpg">';
        $results = $this->subject->check($html, 1);
        self::assertCount(1, $results);
        self::assertSame('error', $results[0]->severity);
        self::assertTrue($results[0]->isError());
    }

    #[Test]
    public function pageUidIsAssignedToResult(): void
    {
        $html = '<img src="photo.jpg">';
        $results = $this->subject->check($html, 42);
        self::assertSame(42, $results[0]->pageUid);
    }

    #[Test]
    public function multipleImagesAreAllChecked(): void
    {
        $html = '<img src="a.jpg"><img src="b.jpg" alt=""><img src="c.jpg" alt="ok">';
        $results = $this->subject->check($html, 1);
        self::assertCount(2, $results);
    }
}
