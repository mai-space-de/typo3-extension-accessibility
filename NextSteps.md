# Next Steps — `mai_accessibility`

## Status: 🔨 In Progress

The scaffolding is complete. The extension has a working backend module, 4 check classes, a service layer, Fluid templates, and unit tests. The following steps are needed to reach a production-ready `✅ Stable` state.

---

## 1. Wire `cms-linkvalidator` Integration

**Why:** FEATURES.md requires broken-link detection surfaced in the accessibility report. The current scaffold checks HTML patterns only — dead links require `cms-linkvalidator`.

**What to do:**

- Create `Classes/Check/BrokenLinkCheck.php` implementing `CheckInterface`
- Inject `TYPO3\CMS\Linkvalidator\Repository\BrokenLinkRepository` and query it by page UID
- Aggregate broken-link records into `CheckResult::error()` entries
- Register the new check in `Configuration/Services.yaml` (add a `calls` entry on `AccessibilityCheckService`)
- Add unit test `Tests/Unit/Check/BrokenLinkCheckTest.php`

---

## 2. Replace Page-Fetch Strategy with Content-Element Query

**Why:** `AccessibilityCheckService::fetchPageHtml()` does a live HTTP request via `SiteFinder` + `RequestFactory`. This is fragile in backend context (auth, base URL, DDEV), slow for many pages, and fails on non-public environments.

**Better approach:**

- Query `tt_content` rows for the target page directly via `ConnectionPool`
- Concatenate `bodytext` and related RTE fields into a single HTML string per page
- Pass that string to the checks instead of the rendered page HTML
- This also makes unit tests for `AccessibilityCheckService` practical

**Affected file:** `Classes/Service/AccessibilityCheckService.php`

---

## 3. Add `AriaAttributeCheckTest`

**Why:** `Tests/Unit/Check/AriaAttributeCheckTest.php` was not created during scaffolding.

**What to test:**
- Unknown `role` value produces error
- Valid `role` produces no result
- `aria-hidden="true"` with `tabindex` produces warning
- `aria-labelledby` referencing missing ID produces error
- Empty HTML returns no results

---

## 4. Scope the Check to a Selected Page Tree

**Why:** Running checks across all published pages will be very slow for large sites. Editors need to scope the check to a subtree or a single page.

**What to do:**

- Add a `pageUid` GET/POST parameter to `indexAction` and `checkAction`
- Use TYPO3's `PageRepository::getMenu()` or a recursive page query to resolve the subtree
- Render a page-tree selector in `Index.html` (TYPO3 `<f:form>` with a page UID input)
- Pass the resolved UIDs down to `AccessibilityCheckService::checkPages()`

---

## 5. Backend Module Icon

**Why:** The module currently inherits the `ext-maispace-mai_accessibility` SVG from `Configuration/Icons.php` but the SVG at `Resources/Public/Icons/Extension.svg` is a placeholder. A dedicated accessibility-themed icon improves UX.

**What to do:**

- Replace `Resources/Public/Icons/Extension.svg` with a meaningful SVG (e.g. an eye or WCAG symbol)
- The icon is auto-registered via `Configuration/Icons.php` — no further wiring needed

---

## 6. Run PHPStan and Fix Baseline

**Why:** PHPStan is configured (`phpstan.neon` exists) but has never been run on the new classes. Some type annotations may need adjustment.

```bash
# inside packages/typo3-extension-accessibility/
composer check:phpstan
# if issues found:
composer check:phpstan:baseline
```

---

## 7. Run Full Lint Check

```bash
# inside packages/typo3-extension-accessibility/
composer lint:check
```

Expected issues to resolve:
- EditorConfig trailing-newline / indent rules
- PHP CS Fixer formatting

---

## 8. Commit and Push Submodule

Once lint and tests pass:

```bash
# inside packages/typo3-extension-accessibility/
git add .
git commit -m "feat: scaffold mai_accessibility backend module"
git push

# then from repo root
git add packages/typo3-extension-accessibility
git commit -m "chore: update mai_accessibility submodule"
```

---

## Completion Criteria for `✅ Stable`

- [ ] `BrokenLinkCheck` implemented and tested
- [ ] Content-element query replaces HTTP fetch in `AccessibilityCheckService`
- [ ] `AriaAttributeCheckTest` written
- [ ] Page-tree scope selector working in the backend module
- [ ] `composer lint:check` passes with zero errors
- [ ] `composer test` passes with all tests green
- [ ] PHPStan baseline clean or documented
- [ ] Submodule committed and pushed
- [ ] Extensions.md updated to `✅ Stable`
