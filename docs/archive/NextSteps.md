# Next Steps — `mai_accessibility`

## Status: 🔨 In Progress

The scaffolding is complete. The extension has a working backend module, 4 check classes, a service layer, Fluid templates, and unit tests. The following steps are needed to reach a production-ready `✅ Stable` state.

Steps are ordered by priority — earlier steps unblock later ones.

---

## 1. Replace Page-Fetch Strategy with Content-Element Query ⚡ (Highest Priority)

**Why:** `AccessibilityCheckService::fetchPageHtml()` does a live HTTP request via `SiteFinder` + `RequestFactory`. This is fragile in backend context (auth, base URL, DDEV), slow for many pages, and fails on non-public environments. Fixing this also makes the service fully unit-testable.

**What to do:**

- Remove `fetchPageHtml()` and the `RequestFactory`/`SiteFinder` constructor dependencies
- Query `tt_content` rows for the target page via `ConnectionPool`
- Include: `bodytext`, `header`, `subheader` (RTE/text fields)
- Also pull image alt texts via `sys_file_reference` joined on `uid_foreign = tt_content.uid`
- Concatenate all fields into a single HTML string per page and pass it to the checks
- This makes `AccessibilityCheckService` fully unit-testable

**Affected file:** `Classes/Service/AccessibilityCheckService.php`

---

## 2. Wire `cms-linkvalidator` Integration

**Why:** FEATURES.md requires broken-link detection surfaced in the accessibility report. The current scaffold checks HTML patterns only — dead links require `cms-linkvalidator`.

**Important prerequisite:** `BrokenLinkRepository` reads *previously stored* results from the linkvalidator crawler — it does not actively follow links. If no linkvalidator scan has been run for a page, the repository returns nothing. The check must surface a clear warning when no linkvalidator data exists, so editors are not misled into thinking there are no broken links.

**What to do:**

- Create `Classes/Check/BrokenLinkCheck.php` implementing `CheckInterface`
- Inject `TYPO3\CMS\Linkvalidator\Repository\BrokenLinkRepository` and query by page UID
- If zero records returned AND no linkvalidator scan has ever run: emit `CheckResult::warning()` with a "Run linkvalidator first" message
- If broken link records exist: emit `CheckResult::error()` per broken link
- Register the new check in `Configuration/Services.yaml`
- Add `Tests/Unit/Check/BrokenLinkCheckTest.php`

---

## 3. Add `AriaAttributeCheckTest`

**Why:** `Tests/Unit/Check/AriaAttributeCheckTest.php` was not created during scaffolding.

**What to test:**
- Empty HTML returns no results
- Unknown `role` value produces error
- Valid `role` produces no result
- `aria-hidden="true"` with `tabindex` produces warning
- `aria-labelledby` referencing a missing ID produces error
- `aria-labelledby` referencing a present ID produces no result

---

## 4. Add `AccessibilityCheckServiceTest`

**Why:** After Step 1 (DB query approach), `AccessibilityCheckService` becomes fully unit-testable via a mocked `ConnectionPool`. Without a test, the orchestration logic is uncovered.

**What to test:**
- `checkPage()` with no `tt_content` rows returns empty array
- `checkPage()` passes concatenated HTML to all registered checks
- `checkPages()` aggregates results keyed by page UID
- Pages with no issues are excluded from the result array

---

## 5. Scope the Check to a Selected Page Tree

**Why:** Running checks across all published pages is slow on large sites. Editors need to scope to a page subtree.

**What to do:**

- Add a `selectedPageUid` request argument to `indexAction` and `checkAction`
- Use a **recursive** page query (not `PageRepository::getMenu()` which only returns direct children) to resolve the full subtree — use `TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction` with a recursive CTE or a helper
- Render a native TYPO3 page-tree web component (`<typo3-backend-page-tree>`) in `Index.html` for page selection — avoid a plain text input
- Pass resolved UIDs to `AccessibilityCheckService::checkPages()`

---

## 6. Fix `ext_emconf.php` state

**Why:** `state` is currently `stable` but the extension is still in progress. Per Instructions.md the layer description says `beta`.

**What to do:**

- Change `"state" => "stable"` to `"state" => "beta"` in `ext_emconf.php`

---

## 7. Backend Module Icon

**Why:** `Resources/Public/Icons/Extension.svg` is a placeholder. A dedicated accessibility-themed icon improves UX.

**What to do:**

- Replace `Resources/Public/Icons/Extension.svg` with a meaningful SVG (e.g. a WCAG "A" symbol or eye with checkmark)
- The icon is auto-registered via `Configuration/Icons.php` — no further wiring needed

---

## 8. Run PHPStan and Fix Baseline

```bash
# inside packages/typo3-extension-accessibility/
composer check:phpstan
# if issues found:
composer check:phpstan:baseline
```

Note: after Step 1 is done, the bare `catch (\Throwable)` in the old `fetchPageHtml` disappears — PHPStan level 8 issues from it resolve automatically.

---

## 9. Run Full Lint Check

```bash
# inside packages/typo3-extension-accessibility/
composer lint:check
```

---

## 10. Commit and Push Submodule

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

- [ ] Content-element DB query replaces HTTP fetch in `AccessibilityCheckService`
- [ ] `BrokenLinkCheck` implemented and tested (with prerequisite warning)
- [ ] `AriaAttributeCheckTest` written
- [ ] `AccessibilityCheckServiceTest` written
- [ ] Page-tree scope selector working (native web component, recursive subtree)
- [ ] `ext_emconf.php` state set to `beta`
- [ ] Extension icon replaced with accessibility-themed SVG
- [ ] `composer lint:check` passes with zero errors
- [ ] `composer test` passes with all tests green
- [ ] PHPStan baseline clean or documented
- [ ] Submodule committed and pushed
- [ ] Extensions.md updated to `✅ Stable`
