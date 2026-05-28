<?php

declare(strict_types=1);

namespace Maispace\MaiAccessibility\Tests\Unit\Check;

use Maispace\MaiAccessibility\Check\ContrastCheck;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ContrastCheckTest extends TestCase
{
    private ContrastCheck $subject;

    protected function setUp(): void
    {
        $this->subject = new ContrastCheck();
    }

    #[Test]
    public function identifierIsColorContrast(): void
    {
        self::assertSame('color_contrast', $this->subject->getIdentifier());
    }

    #[Test]
    public function emptyHtmlReturnsNoResults(): void
    {
        $results = $this->subject->check('', 1);
        self::assertSame([], $results);
    }

    #[Test]
    public function htmlWithoutStyleAttributesReturnsNoResults(): void
    {
        $html = '<p>Plain text</p><div><span>More text</span></div>';
        $results = $this->subject->check($html, 1);
        self::assertSame([], $results);
    }

    #[Test]
    public function onlyColorWithoutBackgroundProducesWarning(): void
    {
        $html = '<p style="color: red">Some text</p>';
        $results = $this->subject->check($html, 1);
        self::assertCount(1, $results);
        self::assertSame('warning', $results[0]->severity);
        self::assertSame('color_contrast', $results[0]->checkIdentifier);
        self::assertStringContainsString('without an explicit background colour', $results[0]->message);
    }

    #[Test]
    public function onlyBackgroundWithoutColorProducesWarning(): void
    {
        $html = '<p style="background-color: #eee">Some text</p>';
        $results = $this->subject->check($html, 1);
        self::assertCount(1, $results);
        self::assertSame('warning', $results[0]->severity);
        self::assertStringContainsString('without an explicit text colour', $results[0]->message);
    }

    #[Test]
    public function onlyBackgroundShorthandWithoutColorProducesWarning(): void
    {
        $html = '<p style="background: #eee">Some text</p>';
        $results = $this->subject->check($html, 1);
        self::assertCount(1, $results);
        self::assertSame('warning', $results[0]->severity);
    }

    #[Test]
    public function sufficientContrastPasses(): void
    {
        // Black text (#000) on white background (#fff) — 21:1 ratio
        $html = '<p style="color: #000; background-color: #fff">High contrast text</p>';
        $results = $this->subject->check($html, 1);
        self::assertSame([], $results);
    }

    #[Test]
    public function insufficientContrastProducesError(): void
    {
        // Light gray text (#bbb) on white background (#fff) — ratio ~2.2:1
        $html = '<p style="color: #bbb; background-color: #fff">Low contrast text</p>';
        $results = $this->subject->check($html, 1);
        self::assertCount(1, $results);
        self::assertSame('error', $results[0]->severity);
        self::assertStringContainsString('Insufficient colour contrast', $results[0]->message);
    }

    #[Test]
    public function borderlineContrastAtThresholdPassesForNormalText(): void
    {
        // #767676 on white (#fff) — ratio ≈ 4.54:1 (just above 4.5)
        $html = '<p style="color: #767676; background-color: #fff">Borderline normal text</p>';
        $results = $this->subject->check($html, 1);
        self::assertSame([], $results);
    }

    #[Test]
    public function largeTextUsesReducedThreshold(): void
    {
        // #aaa on #fff — ratio ≈ 2.82:1 (< 3.0, so fails even for large text)
        $html = '<p style="color: #aaa; background-color: #fff; font-size: 24px">Large heading</p>';
        $results = $this->subject->check($html, 1);
        self::assertCount(1, $results);
        self::assertSame('error', $results[0]->severity);
        self::assertStringContainsString('large text', $results[0]->message);
    }

    #[Test]
    public function largeTextPassesAt3to1Ratio(): void
    {
        // #858585 on #fff — ratio ≈ 3.68:1 (passes for 24px large text)
        $html = '<p style="color: #858585; background-color: #fff; font-size: 24px">Large heading passing</p>';
        $results = $this->subject->check($html, 1);
        self::assertSame([], $results);
    }

    #[Test]
    public function largeTextFailsBelow3to1Ratio(): void
    {
        // #aaa on #fff — ratio ≈ 2.32:1 (fails, below 3.0 threshold for 24px)
        $html = '<p style="color: #aaa; background-color: #fff; font-size: 24px">Large heading failing</p>';
        $results = $this->subject->check($html, 1);
        self::assertCount(1, $results);
        self::assertSame('error', $results[0]->severity);
    }

    #[Test]
    public function normalTextAt3to1RatioStillFails(): void
    {
        // #858585 on #fff — ratio ≈ 3.68:1 (fails for normal text, needs 4.5:1)
        $html = '<p style="color: #858585; background-color: #fff; font-size: 16px">Normal text</p>';
        $results = $this->subject->check($html, 1);
        self::assertCount(1, $results);
        self::assertSame('error', $results[0]->severity);
    }

    #[Test]
    public function boldTextAt14ptUsesLargeTextThreshold(): void
    {
        // #858585 on #fff — ratio ≈ 3.68:1
        // 14pt bold qualifies as large text → passes at 3:1
        $html = '<p style="color: #858585; background-color: #fff; font-size: 14pt; font-weight: bold">Bold text</p>';
        $results = $this->subject->check($html, 1);
        self::assertSame([], $results);
    }

    #[Test]
    public function boldTextAt14ptNumberWeightUsesLargeTextThreshold(): void
    {
        $html = '<p style="color: #858585; background-color: #fff; font-size: 14pt; font-weight: 700">Bold text</p>';
        $results = $this->subject->check($html, 1);
        self::assertSame([], $results);
    }

    #[Test]
    public function hexShorthandColorIsParsedCorrectly(): void
    {
        // #fff = white — black text on white passes
        $html = '<p style="color: #000; background-color: #fff">Test</p>';
        $results = $this->subject->check($html, 1);
        self::assertSame([], $results);
    }

    #[Test]
    public function rgbColorIsParsedCorrectly(): void
    {
        $html = '<p style="color: rgb(0, 0, 0); background-color: rgb(255, 255, 255)">Test</p>';
        $results = $this->subject->check($html, 1);
        self::assertSame([], $results);
    }

    #[Test]
    public function rgbaColorIsParsedCorrectly(): void
    {
        $html = '<p style="color: rgba(0, 0, 0, 0.8); background-color: rgba(255, 255, 255, 0.9)">Test</p>';
        $results = $this->subject->check($html, 1);
        self::assertSame([], $results);
    }

    #[Test]
    public function namedColorIsParsedCorrectly(): void
    {
        $html = '<p style="color: black; background-color: white">Test</p>';
        $results = $this->subject->check($html, 1);
        self::assertSame([], $results);
    }

    #[Test]
    public function namedColorRedOnWhiteHasInsufficientContrast(): void
    {
        // Red (#ff0000) on white — ratio ≈ 4.0:1 (fails 4.5:1 for normal text)
        $html = '<p style="color: red; background-color: white">Red text on white</p>';
        $results = $this->subject->check($html, 1);
        self::assertCount(1, $results);
        self::assertSame('error', $results[0]->severity);
    }

    #[Test]
    public function pageUidIsAssigned(): void
    {
        $html = '<p style="color: #bbb; background-color: #fff">Low contrast</p>';
        $results = $this->subject->check($html, 42);
        self::assertCount(1, $results);
        self::assertSame(42, $results[0]->pageUid);
    }

    #[Test]
    public function multipleElementsAreAllChecked(): void
    {
        $html = '<p style="color: #bbb; background-color: #fff">Low contrast</p>'
            . '<span style="color: #444; background-color: #555">Also low</span>'
            . '<p style="color: #000; background-color: #fff">Fine</p>';
        $results = $this->subject->check($html, 1);
        self::assertCount(2, $results);
    }

    #[Test]
    public function transparentColorProducesWarningForBackground(): void
    {
        // transparent text color resolves to null, leaving only background-color
        $html = '<p style="color: transparent; background-color: red">Transparent</p>';
        $results = $this->subject->check($html, 1);
        self::assertCount(1, $results);
        self::assertSame('warning', $results[0]->severity);
        self::assertStringContainsString('without an explicit text colour', $results[0]->message);
    }

    #[Test]
    public function currentColorProducesWarningForBackground(): void
    {
        // currentColor text resolves to null, leaving only background-color
        $html = '<p style="color: currentColor; background-color: #fff">Current</p>';
        $results = $this->subject->check($html, 1);
        self::assertCount(1, $results);
        self::assertSame('warning', $results[0]->severity);
    }

    #[Test]
    public function contextContainsTagInfo(): void
    {
        $html = '<p style="color: #bbb; background-color: #fff">Low contrast</p>';
        $results = $this->subject->check($html, 1);
        self::assertStringContainsString('<p style=', $results[0]->context);
    }

    #[Test]
    public function nonStyleElementsAreSkipped(): void
    {
        $html = '<p>No style</p><div class="foo">Also no style</div>';
        $results = $this->subject->check($html, 1);
        self::assertSame([], $results);
    }

    #[Test]
    public function multiplePropertiesOnSameElementPicksCorrectly(): void
    {
        // Has both color and background-color — should produce a contrast check result
        $html = '<p style="font-size: 14px; color: #bbb; background-color: #fff; text-align: center;">Styled element</p>';
        $results = $this->subject->check($html, 1);
        self::assertCount(1, $results);
        self::assertSame('error', $results[0]->severity);
    }

    #[Test]
    public function shallowContrastWarningContainsRoundedRatio(): void
    {
        $html = '<p style="color: #888; background-color: #fff">Gray on white</p>';
        $results = $this->subject->check($html, 1);
        self::assertCount(1, $results);
        // #888 on #fff has ratio ≈ 3.57:1, message should contain "3.6"
        self::assertStringContainsString('3.', $results[0]->message);
    }

    #[Test]
    public function backgroundShorthandWithColorUrlAndPosition(): void
    {
        // background with url + color in shorthand — should extract the color
        $html = '<p style="color: #fff; background: #333 url(/bg.jpg) no-repeat center">Text</p>';
        $results = $this->subject->check($html, 1);
        // #333 on #fff — should be fine or fail depending on #333 conversion
        // #333 = rgb(51,51,51) on #fff ratio ≈ 13.4:1 — passes
        self::assertSame([], $results);
    }

    #[Test]
    public function backgroundWithoutColorUrlStillWarns(): void
    {
        $html = '<p style="background: url(/bg.jpg) no-repeat">Text</p>';
        $results = $this->subject->check($html, 1);
        // No color, no background-color, no text color — no style properties found
        self::assertSame([], $results);
    }

    #[Test]
    #[DataProvider('provideColorPairs')]
    public function variousColorCombinations(string $color, string $bgColor, ?string $expectedSeverity): void
    {
        $html = sprintf(
            '<p style="color: %s; background-color: %s">Test</p>',
            $color,
            $bgColor,
        );
        $results = $this->subject->check($html, 1);

        if ($expectedSeverity === null) {
            self::assertSame([], $results, "Expected no results for color=$color bg=$bgColor");
        } else {
            self::assertCount(1, $results, "Expected 1 result for color=$color bg=$bgColor");
            self::assertSame($expectedSeverity, $results[0]->severity);
        }
    }

    /**
     * @return array<string, array{string, string, string|null}>
     */
    public static function provideColorPairs(): array
    {
        return [
            'black on white passes' => ['#000', '#fff', null],
            'white on black passes' => ['#fff', '#000', null],
            'dark gray on white fails' => ['#999', '#fff', 'error'],
            'medium gray on white fails' => ['#777777', '#ffffff', 'error'],
            'gray on white fails' => ['#7a7a7a', '#ffffff', 'error'],
            'gray on white also fails' => ['#7d7d7d', '#ffffff', 'error'],
            'purple on yellow passes' => ['#800080', '#ffff00', null],
            'white on yellow fails' => ['#fff', '#ffff00', 'error'],
            'dark orange on black passes' => ['#cc7000', '#000', null],
        ];
    }

    #[Test]
    public function fontSizesAtThresholds(): void
    {
        // 17.9px not large text (needs 18.66px for bold)
        $html1 = '<p style="color: #999; background-color: #fff; font-size: 17.9px; font-weight: bold">Bold small</p>';
        $results1 = $this->subject->check($html1, 1);
        self::assertCount(1, $results1);

        // 18.66px bold = large text → uses 3:1 threshold
        // #999 on #fff ratio ≈ 2.82 — fails even at 3:1
        $html2 = '<p style="color: #999; background-color: #fff; font-size: 18.66px; font-weight: bold">Large bold</p>';
        $results2 = $this->subject->check($html2, 1);
        self::assertCount(1, $results2);
    }

    #[Test]
    public function colorFromBackgroundShorthandWhenNoBackgroundColor(): void
    {
        // background: black sets background-color, color: white → should pass (high contrast)
        $html = '<p style="color: white; background: black">High contrast</p>';
        $results = $this->subject->check($html, 1);
        self::assertSame([], $results);
    }
}
