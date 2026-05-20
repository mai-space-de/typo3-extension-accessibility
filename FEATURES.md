# mai_accessibility — Feature Reference

> Admin-only backend tool for automated WCAG-oriented content checks.
> Scans page content via database queries (no frontend render required).

---

## 1. Backend Module

Registered under the **Tools** group (`parent: tools`), accessible to admin users only.

| Action | Route | Description |
| --- | --- | --- |
| `index` | `/module/mai-accessibility` | Overview page — shows scope selector and page list |
| `check` | `/module/mai-accessibility/check` | Runs all registered checks against the selected pages |
| `exportCsv` | `/module/mai-accessibility/export-csv` | Downloads check results as a CSV file |

Icon: `mai-backend-module` (→ `EXT:mai_base/Resources/Public/Icons/generic_backend_module.svg`).

---

## 2. Page-tree Scope Selector

The index page shows a **Root page** dropdown populated from all visible root pages
(doktype=1, pid=0, not deleted/hidden). The editor selects a root page to limit the
check to that subtree, or leaves the selector at "All pages" to scan every visible
normal page in the installation.

**Subtree resolution** — when a root page UID is supplied via `?rootPageUid=N`:

1. `PageTreeRepository::getFlattenedPages([N], 20)` fetches up to 20 levels deep.
2. The root page itself is prepended to the result.
3. Only doktype=1 (normal) pages are kept; doktype=4 (shortcut), 6 (backend user section),
   254 (folder), etc. are excluded.

**All-pages fallback** — when `rootPageUid` is absent or 0:
`fetchAllCheckablePages()` runs a direct query that returns all
non-deleted, non-hidden, doktype=1 pages ordered by UID.

The selected `rootPageUid` is threaded through to all three actions so that
`check` and `exportCsv` operate on the same page set as the `index` preview.

---

## 3. Accessibility Checks

Five checks are registered by default via the `mai_accessibility.check` DI tag
(see §5). Each implements `CheckInterface` and returns zero or more `CheckResult`
value objects.

| Identifier | Class | What it checks |
| --- | --- | --- |
| `alt-text` | `AltTextCheck` | Images whose `alt` attribute is absent or empty |
| `heading-structure` | `HeadingStructureCheck` | Skipped heading levels (e.g. h1 → h3, no h2) |
| `aria-attributes` | `AriaAttributeCheck` | Elements with `role` but missing required ARIA attributes |
| `link-text` | `LinkTextCheck` | Anchors with non-descriptive text ("click here", "more", …) |
| `broken-links` | `BrokenLinkCheck` | Pages flagged by `cms-linkvalidator`; returns a warning when `cms-linkvalidator` is not installed |

---

## 4. Content Analysis Pipeline

`AccessibilityCheckService::checkPage(int $pageUid)` assembles a synthetic HTML
string from the database and passes it to every registered check.

```
┌─────────────────────────────────────────────────────┐
│  checkPage($pageUid)                                │
│                                                     │
│  1. fetchContentElements($pageUid)                  │
│     SELECT header, subheader, bodytext              │
│     FROM tt_content                                 │
│     WHERE pid=? AND deleted=0 AND hidden=0          │
│     ORDER BY sorting                                │
│                                                     │
│     → wraps header in <h2>, subheader in <h3>,      │
│       bodytext as raw HTML                          │
│                                                     │
│  2. fetchImageAltTexts($pageUid)                    │
│     SELECT r.alternative, f.identifier              │
│     FROM sys_file_reference r                       │
│       JOIN sys_file f ON r.uid_local = f.uid        │
│       JOIN tt_content c ON r.uid_foreign = c.uid    │
│     WHERE c.pid=? … tablenames='tt_content'         │
│           fieldname='image'                         │
│                                                     │
│     → <img src="…"> (no alt) or                    │
│       <img src="…" alt="…"> (with alt)             │
│                                                     │
│  3. Concatenates all parts with "\n"                │
│  4. Iterates checks; collects CheckResult[]         │
└─────────────────────────────────────────────────────┘
```

**Limitation** — the HTML is synthetic: it contains only `tt_content` headers,
body text, and directly attached images. Custom content elements, nested
containers, and plugin-rendered output are not analysed.

---

## 5. Check Registration

Checks are registered as Symfony service tags in `Configuration/Services.yaml`:

```yaml
Maispace\MaiAccessibility\Check\AltTextCheck:
  tags:
    - name: 'mai_accessibility.check'
```

`AccessibilityCheckService` is configured via `calls:` to receive each check
via `addCheck(CheckInterface $check)`, which inserts the check into an internal
`array<string, CheckInterface>` keyed by `$check->getIdentifier()`.

To add a custom check in a downstream extension:
1. Implement `CheckInterface`.
2. Tag the service with `name: 'mai_accessibility.check'`.
3. Add it to `AccessibilityCheckService`'s `calls:` list **in the downstream
   extension's `Services.yaml`** (or use a TYPO3 compiler pass if dynamic
   registration is required).

---

## 6. CheckResult Value Object

`CheckResult` is a read-only value object returned by every check.

| Property | Type | Description |
| --- | --- | --- |
| `checkIdentifier` | `string` | Matches the check's `getIdentifier()` return value |
| `severity` | `'error'\|'warning'\|'info'` | Severity level |
| `message` | `string` | Human-readable description of the issue |
| `context` | `string` | Snippet of the offending HTML fragment for context |

Helper factories:
- `CheckResult::error($checkIdentifier, $message, $context)` → severity `error`
- `CheckResult::warning($checkIdentifier, $message, $context)` → severity `warning`
- `CheckResult::info($checkIdentifier, $message, $context)` → severity `info`

`isError()` returns true only for `error` severity; `isWarning()` returns true only
for `warning` severity. The check result view in the backend colours rows
`table-danger` / `table-warning` / `table-info` accordingly.

---

## 7. CSV Export

`exportCsvAction()` runs the same check pipeline as `checkAction()` and streams
a CSV download named `accessibility-report.csv`.

Column order:

| Column | Content |
| --- | --- |
| `Page UID` | Integer UID of the page |
| `Check` | Check identifier (e.g. `alt-text`) |
| `Severity` | `error`, `warning`, or `info` |
| `Message` | Full message text |
| `Context` | HTML snippet (same as the `context` column in the UI) |

The export scope respects the `rootPageUid` parameter so the CSV always matches
the on-screen results.

---

## 8. Architecture Constraints

- **Admin-only** — the module is `access: admin`; no frontend exposure.
- **No custom tables** — all data is read from `tt_content`, `sys_file_reference`,
  `sys_file`, `pages`, and `tx_linkvalidator_broken_links`. Nothing is written.
- **Database-driven** — checks run against stored content, not against a rendered
  HTTP response. A page's rendered output may differ from what the checks see.
- **Shared icon** — uses `mai-backend-module` (provided by `mai_base`); never
  declare its own backend module icon SVG.
- **Layer** — Developer Tools layer; no dependency on Feature-layer extensions.
