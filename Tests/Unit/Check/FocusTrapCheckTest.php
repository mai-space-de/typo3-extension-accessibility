<?php

declare(strict_types=1);

namespace Maispace\MaiAccessibility\Tests\Unit\Check;

use Maispace\MaiAccessibility\Check\FocusTrapCheck;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class FocusTrapCheckTest extends TestCase
{
    private FocusTrapCheck $subject;

    protected function setUp(): void
    {
        $this->subject = new FocusTrapCheck();
    }

    #[Test]
    public function identifierIsFocusTrap(): void
    {
        self::assertSame('focus_trap', $this->subject->getIdentifier());
    }

    #[Test]
    public function emptyHtmlReturnsNoResults(): void
    {
        self::assertSame([], $this->subject->check('', 1));
    }

    #[Test]
    public function htmlWithoutInteractiveElementsReturnsNoResults(): void
    {
        $html = '<p>Plain text</p><div><span>More content</span></div>';
        self::assertSame([], $this->subject->check($html, 1));
    }

    #[Test]
    public function modalDialogWithoutFocusableContentProducesError(): void
    {
        $html = '<div role="dialog" aria-label="Search modal"><p>No interactive elements</p></div>';
        $results = $this->subject->check($html, 1);

        self::assertCount(1, $results);
        self::assertSame('error', $results[0]->severity);
        self::assertSame('focus_trap', $results[0]->checkIdentifier);
        self::assertStringContainsString('no focusable elements', $results[0]->message);
        self::assertStringContainsString('dialog', $results[0]->context);
    }

    #[Test]
    public function modalDialogWithFocusableContentProducesNoError(): void
    {
        $html = '<div role="dialog" aria-label="Search modal">'
            . '<input type="text" placeholder="Search...">'
            . '<button>Search</button>'
            . '</div>';
        $results = $this->subject->check($html, 1);

        self::assertSame([], $results);
    }

    #[Test]
    public function alertdialogWithoutFocusableContentProducesError(): void
    {
        $html = '<div role="alertdialog" aria-label="Confirm delete">'
            . '<p>Are you sure?</p>'
            . '</div>';
        $results = $this->subject->check($html, 1);

        self::assertCount(1, $results);
        self::assertSame('error', $results[0]->severity);
        self::assertStringContainsString('alertdialog', $results[0]->context);
    }

    #[Test]
    public function alertdialogWithButtonProducesNoError(): void
    {
        $html = '<div role="alertdialog" aria-label="Confirm">'
            . '<p>Are you sure?</p>'
            . '<button>Yes</button><button>No</button>'
            . '</div>';
        $results = $this->subject->check($html, 1);

        self::assertSame([], $results);
    }

    #[Test]
    public function ariaHiddenContainerWithFocusableChildrenProducesWarning(): void
    {
        $html = '<div aria-hidden="true" id="offcanvas">'
            . '<a href="/somewhere">Link</a>'
            . '</div>';
        $results = $this->subject->check($html, 1);

        self::assertCount(1, $results);
        self::assertSame('warning', $results[0]->severity);
        self::assertSame('focus_trap', $results[0]->checkIdentifier);
        self::assertStringContainsString('aria-hidden', $results[0]->message);
        self::assertStringContainsString('focusable', $results[0]->message);
    }

    #[Test]
    public function ariaHiddenContainerWithoutFocusableChildrenProducesNoResult(): void
    {
        $html = '<div aria-hidden="true">'
            . '<p>Just decorative text</p>'
            . '<span>More text</span>'
            . '</div>';
        $results = $this->subject->check($html, 1);

        self::assertSame([], $results);
    }

    #[Test]
    public function ariaHiddenWithButtonProducesWarning(): void
    {
        $html = '<div aria-hidden="true"><button>Hidden button</button></div>';
        $results = $this->subject->check($html, 1);

        self::assertCount(1, $results);
        self::assertSame('warning', $results[0]->severity);
    }

    #[Test]
    public function ariaHiddenWithInputProducesWarning(): void
    {
        $html = '<div aria-hidden="true"><input type="text"></div>';
        $results = $this->subject->check($html, 1);

        self::assertCount(1, $results);
        self::assertSame('warning', $results[0]->severity);
    }

    #[Test]
    public function ariaHiddenWithSelectProducesWarning(): void
    {
        $html = '<div aria-hidden="true"><select><option>1</option></select></div>';
        $results = $this->subject->check($html, 1);

        self::assertCount(1, $results);
        self::assertSame('warning', $results[0]->severity);
    }

    #[Test]
    public function ariaHiddenWithTextareaProducesWarning(): void
    {
        $html = '<div aria-hidden="true"><textarea></textarea></div>';
        $results = $this->subject->check($html, 1);

        self::assertCount(1, $results);
        self::assertSame('warning', $results[0]->severity);
    }

    #[Test]
    public function ariaHiddenWithFocusableTabindexProducesWarning(): void
    {
        $html = '<div aria-hidden="true"><span tabindex="0">Focusable span</span></div>';
        $results = $this->subject->check($html, 1);

        self::assertCount(1, $results);
        self::assertSame('warning', $results[0]->severity);
    }

    #[Test]
    public function ariaHiddenSpanWithTabindexMinusOneIsNotFocusable(): void
    {
        $html = '<div aria-hidden="true"><span tabindex="-1">Not focusable</span></div>';
        $results = $this->subject->check($html, 1);

        self::assertSame([], $results);
    }

    #[Test]
    public function ariaHiddenContainsOnlyDisabledButtonProducesNoWarning(): void
    {
        $html = '<div aria-hidden="true"><button disabled>Disabled button</button></div>';
        $results = $this->subject->check($html, 1);

        self::assertSame([], $results);
    }

    #[Test]
    public function ariaHiddenWithHiddenInputTypeProducesNoWarning(): void
    {
        $html = '<div aria-hidden="true"><input type="hidden" name="foo" value="bar"></div>';
        $results = $this->subject->check($html, 1);

        self::assertSame([], $results);
    }

    #[Test]
    public function ariaHiddenWithAnchorWithoutHrefProducesNoWarning(): void
    {
        $html = '<div aria-hidden="true"><a>Placeholder anchor</a></div>';
        $results = $this->subject->check($html, 1);

        self::assertSame([], $results);
    }

    #[Test]
    public function tabpanelWithoutFocusableContentProducesWarning(): void
    {
        $html = '<div role="tabpanel" id="panel1"><p>No interactive content</p></div>';
        $results = $this->subject->check($html, 1);

        self::assertCount(1, $results);
        self::assertSame('warning', $results[0]->severity);
        self::assertSame('focus_trap', $results[0]->checkIdentifier);
        self::assertStringContainsString('tabpanel', $results[0]->message);
    }

    #[Test]
    public function tabpanelWithFocusableContentProducesNoWarning(): void
    {
        $html = '<div role="tabpanel" id="panel1">'
            . '<p>Content</p>'
            . '<a href="/detail">Read more</a>'
            . '</div>';
        $results = $this->subject->check($html, 1);

        self::assertSame([], $results);
    }

    #[Test]
    public function pageUidIsAssignedToResult(): void
    {
        $html = '<div role="dialog" aria-label="Test"><p>No focusable</p></div>';
        $results = $this->subject->check($html, 42);

        self::assertCount(1, $results);
        self::assertSame(42, $results[0]->pageUid);
    }

    #[Test]
    public function multipleModalsAreAllChecked(): void
    {
        $html = '<div role="dialog" aria-label="Modal A"><p>No focusable</p></div>'
            . '<div role="dialog" aria-label="Modal B"><p>Also no focusable</p></div>'
            . '<div role="dialog" aria-label="Modal C"><button>OK</button></div>';
        $results = $this->subject->check($html, 1);

        self::assertCount(2, $results);
    }

    #[Test]
    public function multipleIssuesReportedForSameHtml(): void
    {
        $html = '<div role="dialog" aria-label="Trapped"><p>No focusable</p></div>'
            . '<div aria-hidden="true"><a href="/hidden">Hidden link</a></div>';
        $results = $this->subject->check($html, 1);

        self::assertCount(2, $results);

        $errors = array_filter($results, fn($r) => $r->severity === 'error');
        $warnings = array_filter($results, fn($r) => $r->severity === 'warning');

        self::assertCount(1, $errors);
        self::assertCount(1, $warnings);
    }

    #[Test]
    public function contextContainsTagInfoForModal(): void
    {
        $html = '<div role="dialog" aria-label="My Modal"><p>No focusable</p></div>';
        $results = $this->subject->check($html, 1);

        self::assertStringContainsString('aria-label="My Modal"', $results[0]->context);
    }

    #[Test]
    public function contextContainsTagInfoForAriaHidden(): void
    {
        $html = '<nav aria-hidden="true" id="mobile-menu"><a href="/">Home</a></nav>';
        $results = $this->subject->check($html, 1);

        self::assertStringContainsString('id="mobile-menu"', $results[0]->context);
    }

    #[Test]
    public function nestedFocusableInDeepDomIsDetected(): void
    {
        $html = '<div role="dialog" aria-label="Deep">'
            . '<div><div><div><p>Deeply nested</p></div></div></div>'
            . '</div>';
        $results = $this->subject->check($html, 1);

        // No focusable elements within the dialog
        self::assertCount(1, $results);
        self::assertSame('error', $results[0]->severity);
    }

    #[Test]
    public function nestedFocusableInDeepDomIsDetectedAsPresent(): void
    {
        $html = '<div role="dialog" aria-label="Deep">'
            . '<div><div><div><button>Nested button</button></div></div></div>'
            . '</div>';
        $results = $this->subject->check($html, 1);

        self::assertSame([], $results);
    }

    #[Test]
    public function videoWithControlsIsFocusable(): void
    {
        $html = '<div aria-hidden="true"><video controls><source src="vid.mp4"></video></div>';
        $results = $this->subject->check($html, 1);

        self::assertCount(1, $results);
        self::assertSame('warning', $results[0]->severity);
    }

    #[Test]
    public function videoWithoutControlsIsNotFocusable(): void
    {
        $html = '<div aria-hidden="true"><video><source src="vid.mp4"></video></div>';
        $results = $this->subject->check($html, 1);

        self::assertSame([], $results);
    }

    #[Test]
    public function modalWithLinkIsFocusable(): void
    {
        $html = '<div role="dialog" aria-label="Nav modal"><a href="/page">Link</a></div>';
        $results = $this->subject->check($html, 1);

        self::assertSame([], $results);
    }

    #[Test]
    public function modalWithSelectIsFocusable(): void
    {
        $html = '<div role="dialog" aria-label="Form modal">'
            . '<select><option>Option</option></select>'
            . '</div>';
        $results = $this->subject->check($html, 1);

        self::assertSame([], $results);
    }

    #[Test]
    public function modalWithTextareaIsFocusable(): void
    {
        $html = '<div role="dialog" aria-label="Text modal"><textarea></textarea></div>';
        $results = $this->subject->check($html, 1);

        self::assertSame([], $results);
    }

    #[Test]
    public function modalWithTabindexSpanIsFocusable(): void
    {
        $html = '<div role="dialog" aria-label="Custom focus">'
            . '<span tabindex="0">Custom focusable</span>'
            . '</div>';
        $results = $this->subject->check($html, 1);

        self::assertSame([], $results);
    }

    #[Test]
    #[DataProvider('provideDialogVariants')]
    public function allDialogVariantsDetected(string $role): void
    {
        $html = sprintf('<div role="%s" aria-label="Test %s"><p>Content</p></div>', $role, $role);
        $results = $this->subject->check($html, 1);

        self::assertCount(1, $results);
        self::assertSame('error', $results[0]->severity);
        self::assertStringContainsString($role, $results[0]->context);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function provideDialogVariants(): array
    {
        return [
            'dialog' => ['dialog'],
            'alertdialog' => ['alertdialog'],
        ];
    }
}
