<?php

declare(strict_types=1);

namespace Maispace\MaiAccessibility\Tests\Unit\Check;

use Maispace\MaiAccessibility\Check\LandmarksCheck;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class LandmarksCheckTest extends TestCase
{
    private LandmarksCheck $subject;

    protected function setUp(): void
    {
        $this->subject = new LandmarksCheck();
    }

    #[Test]
    public function identifierIsLandmarks(): void
    {
        self::assertSame('landmarks', $this->subject->getIdentifier());
    }

    #[Test]
    public function emptyHtmlReturnsNoResults(): void
    {
        self::assertSame([], $this->subject->check('', 1));
    }

    #[Test]
    public function htmlWithoutLandmarksReturnsNoResults(): void
    {
        $html = '<p>Some content</p><div><span>More</span></div>';
        self::assertSame([], $this->subject->check($html, 1));
    }

    #[Test]
    public function mainLandmarkViaRoleIsDetected(): void
    {
        $html = '<div role="main"><p>Content</p></div>';
        $results = $this->subject->check($html, 1);
        $infos = array_values(array_filter($results, static fn($r) => $r->severity === 'info'));
        self::assertCount(1, $infos);
        self::assertStringContainsString('main', $infos[0]->message);
    }

    #[Test]
    public function navigationLandmarkViaRoleIsDetected(): void
    {
        $html = '<div role="navigation"><a href="/">Home</a></div>';
        $results = $this->subject->check($html, 1);
        $infos = array_values(array_filter($results, static fn($r) => $r->severity === 'info'));
        self::assertCount(1, $infos);
        self::assertStringContainsString('navigation', $infos[0]->message);
    }

    #[Test]
    public function mainLandmarkViaSemanticElementIsDetected(): void
    {
        $html = '<main><p>Content</p></main>';
        $results = $this->subject->check($html, 1);
        $infos = array_values(array_filter($results, static fn($r) => $r->severity === 'info'));
        self::assertCount(1, $infos);
        self::assertStringContainsString('main', $infos[0]->message);
    }

    #[Test]
    public function navElementIsDetectedAsNavigation(): void
    {
        $html = '<nav><a href="/">Home</a></nav>';
        $results = $this->subject->check($html, 1);
        $infos = array_values(array_filter($results, static fn($r) => $r->severity === 'info'));
        self::assertCount(1, $infos);
        self::assertStringContainsString('navigation', $infos[0]->message);
    }

    #[Test]
    public function asideElementIsDetectedAsComplementary(): void
    {
        $html = '<aside><p>Sidebar</p></aside>';
        $results = $this->subject->check($html, 1);
        $infos = array_values(array_filter($results, static fn($r) => $r->severity === 'info'));
        self::assertCount(1, $infos);
        self::assertStringContainsString('complementary', $infos[0]->message);
    }

    #[Test]
    public function formElementIsDetectedAsFormLandmark(): void
    {
        $html = '<form><label>Name <input type="text"></label></form>';
        $results = $this->subject->check($html, 1);
        $infos = array_values(array_filter($results, static fn($r) => $r->severity === 'info'));
        self::assertCount(1, $infos);
        self::assertStringContainsString('form', $infos[0]->message);
    }

    #[Test]
    public function searchElementIsDetectedAsSearchLandmark(): void
    {
        $html = '<search><input type="search" placeholder="Search..."></search>';
        $results = $this->subject->check($html, 1);
        $infos = array_values(array_filter($results, static fn($r) => $r->severity === 'info'));
        self::assertCount(1, $infos);
        self::assertStringContainsString('search', $infos[0]->message);
    }

    #[Test]
    public function implicitElementWithExplicitRoleIsNotDoubleCounted(): void
    {
        $html = '<nav role="navigation"><a href="/">Home</a></nav>';
        $results = $this->subject->check($html, 1);
        $infos = array_values(array_filter($results, static fn($r) => $r->severity === 'info'));
        self::assertCount(1, $infos);
    }

    #[Test]
    public function multipleLandmarksAreAllReported(): void
    {
        $html = '<header role="banner"><h1>Site</h1></header>'
            . '<nav><a href="/">Home</a></nav>'
            . '<main><p>Content</p></main>'
            . '<aside><p>Sidebar</p></aside>'
            . '<footer role="contentinfo"><p>Footer</p></footer>';
        $results = $this->subject->check($html, 1);
        $infos = array_values(array_filter($results, static fn($r) => $r->severity === 'info'));
        self::assertCount(5, $infos);
        $messages = implode(' ', array_map(static fn($r) => $r->message, $infos));
        self::assertStringContainsString('banner', $messages);
        self::assertStringContainsString('navigation', $messages);
        self::assertStringContainsString('main', $messages);
        self::assertStringContainsString('complementary', $messages);
        self::assertStringContainsString('contentinfo', $messages);
    }

    #[Test]
    public function pageWithOnlyBannerAndContentinfoReturnsNoErrorsOrWarnings(): void
    {
        $html = '<header role="banner"><h1>Site</h1></header>'
            . '<footer role="contentinfo"><p>Footer</p></footer>';
        $results = $this->subject->check($html, 1);
        $errors = array_filter($results, static fn($r) => $r->severity === 'error');
        $warnings = array_filter($results, static fn($r) => $r->severity === 'warning');
        self::assertCount(0, $errors);
        self::assertCount(0, $warnings);
    }

    #[Test]
    public function duplicateMainLandmarksProducesError(): void
    {
        $html = '<main><p>First</p></main><main><p>Second</p></main>';
        $results = $this->subject->check($html, 1);
        $errors = array_values(array_filter($results, static fn($r) => $r->severity === 'error'));
        self::assertGreaterThanOrEqual(1, count($errors));
        self::assertStringContainsString('Multiple main landmarks', $errors[0]->message);
    }

    #[Test]
    public function duplicateBannerLandmarksProducesError(): void
    {
        $html = '<header role="banner"><h1>A</h1></header>'
            . '<div role="banner"><h1>B</h1></div>';
        $results = $this->subject->check($html, 1);
        $errors = array_values(array_filter($results, static fn($r) => $r->severity === 'error'));
        self::assertGreaterThanOrEqual(1, count($errors));
        self::assertStringContainsString('Multiple banner landmarks', $errors[0]->message);
    }

    #[Test]
    public function duplicateContentinfoLandmarksProducesError(): void
    {
        $html = '<footer role="contentinfo"><p>A</p></footer>'
            . '<div role="contentinfo"><p>B</p></div>';
        $results = $this->subject->check($html, 1);
        $errors = array_values(array_filter($results, static fn($r) => $r->severity === 'error'));
        self::assertGreaterThanOrEqual(1, count($errors));
        self::assertStringContainsString('Multiple contentinfo landmarks', $errors[0]->message);
    }

    #[Test]
    public function duplicateNavigationsWithoutLabelsProducesWarning(): void
    {
        $html = '<nav><a href="/">Main</a></nav><nav><a href="/about">About</a></nav>';
        $results = $this->subject->check($html, 1);
        $labelWarnings = array_filter(
            $results,
            static fn($r) => str_contains($r->message ?? '', 'without distinguishing labels'),
        );
        self::assertGreaterThanOrEqual(1, count($labelWarnings));
    }

    #[Test]
    public function duplicateNavigationsWithLabelsProduceNoWarning(): void
    {
        $html = '<nav aria-label="Main"><a href="/">Home</a></nav>'
            . '<nav aria-label="Footer"><a href="/legal">Legal</a></nav>';
        $results = $this->subject->check($html, 1);
        $labelWarnings = array_filter(
            $results,
            static fn($r) => str_contains($r->message ?? '', 'without distinguishing labels'),
        );
        self::assertCount(0, $labelWarnings);
    }

    #[Test]
    public function duplicateFormLandmarksWithoutLabelsProducesWarning(): void
    {
        $html = '<form><input type="text" name="a"></form><form><input type="text" name="b"></form>';
        $results = $this->subject->check($html, 1);
        $labelWarnings = array_filter(
            $results,
            static fn($r) => str_contains($r->message ?? '', 'without distinguishing labels'),
        );
        self::assertGreaterThanOrEqual(1, count($labelWarnings));
    }

    #[Test]
    public function singleNavigationProducesNoLabelWarning(): void
    {
        $html = '<nav><a href="/">Home</a></nav>';
        $results = $this->subject->check($html, 1);
        $labelWarnings = array_filter(
            $results,
            static fn($r) => str_contains($r->message ?? '', 'without distinguishing labels'),
        );
        self::assertCount(0, $labelWarnings);
    }

    #[Test]
    public function pageUidIsAssignedToResults(): void
    {
        $html = '<main><p>Content</p></main>';
        $results = $this->subject->check($html, 42);
        $infos = array_values(array_filter($results, static fn($r) => $r->severity === 'info'));
        self::assertGreaterThanOrEqual(1, count($infos));
        self::assertSame(42, $infos[0]->pageUid);
    }

    #[Test]
    public function contextContainsTagInfoForLandmark(): void
    {
        $html = '<nav class="main-nav" aria-label="Primary"><a href="/">Home</a></nav>';
        $results = $this->subject->check($html, 1);
        $infos = array_values(array_filter($results, static fn($r) => $r->severity === 'info'));
        self::assertStringContainsString('nav', $infos[0]->context);
        self::assertStringContainsString('main-nav', $infos[0]->context);
    }

    #[Test]
    public function landmarkWithAriaLabelIncludesLabelInInfo(): void
    {
        $html = '<nav aria-label="Main navigation"><a href="/">Home</a></nav>';
        $results = $this->subject->check($html, 1);
        $infos = array_values(array_filter($results, static fn($r) => $r->severity === 'info'));
        self::assertStringContainsString('labelled "Main navigation"', $infos[0]->message);
    }

    #[Test]
    public function wellStructuredPageWithMultipleLandmarksProducesNoErrorsOrWarnings(): void
    {
        $html = '<main><p>Content</p></main>'
            . '<nav aria-label="Main"><a href="/">Home</a></nav>'
            . '<aside aria-label="Related"><p>Related</p></aside>';
        $results = $this->subject->check($html, 1);
        $errors = array_filter($results, static fn($r) => $r->severity === 'error');
        $warnings = array_filter($results, static fn($r) => $r->severity === 'warning');
        self::assertCount(0, $errors);
        self::assertCount(0, $warnings);
    }

    #[Test]
    public function landmarkWithAriaLabelledbyIsRecognised(): void
    {
        $html = '<span id="nav-label" hidden>Site navigation</span>'
            . '<nav aria-labelledby="nav-label"><a href="/">Home</a></nav>';
        $results = $this->subject->check($html, 1);
        $infos = array_values(array_filter($results, static fn($r) => $r->severity === 'info'));
        self::assertStringContainsString('nav-label', $infos[0]->context);
    }

    #[Test]
    public function whitespaceOnlyHtmlReturnsNoResults(): void
    {
        self::assertSame([], $this->subject->check("   \n  \t  ", 1));
    }

    #[Test]
    public function landmarkWithClassAndRolePreservesRoleInTag(): void
    {
        $html = '<div role="banner" class="site-header"><h1>Site</h1></div>';
        $results = $this->subject->check($html, 1);
        $infos = array_values(array_filter($results, static fn($r) => $r->severity === 'info'));
        self::assertStringContainsString('role="banner"', $infos[0]->context);
    }
}
