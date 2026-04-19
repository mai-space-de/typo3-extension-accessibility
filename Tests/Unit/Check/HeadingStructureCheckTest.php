<?php

declare(strict_types=1);

namespace Maispace\MaiAccessibility\Tests\Unit\Check;

use Maispace\MaiAccessibility\Check\HeadingStructureCheck;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class HeadingStructureCheckTest extends TestCase
{
    private HeadingStructureCheck $subject;

    protected function setUp(): void
    {
        $this->subject = new HeadingStructureCheck();
    }

    #[Test]
    public function identifierIsHeadingStructure(): void
    {
        self::assertSame('heading_structure', $this->subject->getIdentifier());
    }

    #[Test]
    public function emptyHtmlReturnsNoResults(): void
    {
        self::assertSame([], $this->subject->check('', 1));
    }

    #[Test]
    public function htmlWithoutHeadingsReturnsNoResults(): void
    {
        $results = $this->subject->check('<p>No headings here.</p>', 1);
        self::assertSame([], $results);
    }

    #[Test]
    public function validHeadingHierarchyProducesNoResults(): void
    {
        $html = '<h1>Title</h1><h2>Section</h2><h3>Subsection</h3>';
        self::assertSame([], $this->subject->check($html, 1));
    }

    #[Test]
    public function skippedHeadingLevelProducesError(): void
    {
        $html = '<h1>Title</h1><h3>Skipped h2</h3>';
        $results = $this->subject->check($html, 1);
        self::assertCount(1, $results);
        self::assertSame('error', $results[0]->severity);
    }

    #[Test]
    public function missingH1ProducesWarning(): void
    {
        $html = '<h2>Section</h2><h3>Subsection</h3>';
        $results = $this->subject->check($html, 1);
        self::assertCount(1, $results);
        self::assertSame('warning', $results[0]->severity);
    }

    #[Test]
    public function multipleH1ProducesWarning(): void
    {
        $html = '<h1>First</h1><h1>Second</h1>';
        $results = $this->subject->check($html, 1);
        self::assertCount(1, $results);
        self::assertSame('warning', $results[0]->severity);
    }
}
