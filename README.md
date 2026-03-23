# maispace/accessibility — TYPO3 Accessibility

[![CI](https://github.com/mai-space-de/typo3-extension-accessibility/actions/workflows/ci.yml/badge.svg?branch=main)](https://github.com/mai-space-de/typo3-extension-accessibility/actions/workflows/ci.yml)
[![PHP](https://img.shields.io/badge/PHP-8.2%2B-blue)](https://www.php.net/)
[![TYPO3](https://img.shields.io/badge/TYPO3-13.4%20LTS-orange)](https://typo3.org/)
[![License: GPL v2](https://img.shields.io/badge/License-GPL%20v2-blue.svg)](https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html)

A TYPO3 extension that adds backend accessibility analysis modules to help editors identify and fix accessibility issues in editorial content — covering alt texts, ARIA attributes, heading structure, and link texts.

**Requires:** TYPO3 13.4 LTS · PHP 8.2+

---

## Features at a glance

| Feature | Description |
|---|---|
| Alt text analysis | Identifies images missing descriptive alternative texts |
| ARIA attributes check | Detects missing or malformed ARIA roles and attributes |
| Heading structure analysis | Flags incorrect heading hierarchy in content |
| Link text check | Finds non-descriptive link texts (e.g. "click here", "read more") |
| Backend modules | Dedicated TYPO3 backend modules for each accessibility check |

---

## Installation

```bash
composer require maispace/mai-accessibility
```

TYPO3 will automatically discover the extension. No manual activation is required.

---

## Development

### Running tests

```bash
composer install
composer test
```

### CI

| Job | What it checks |
|---|---|
| `composer-validate` | `composer.json` is valid and well-formed |
| `unit-tests` | PHPUnit suite across PHP 8.2 / 8.3 × TYPO3 13.4 |
| `static-analysis` | PHPStan (`phpstan.neon`, level max) |
| `code-style` | EditorConfig + PHP-CS-Fixer |
| `typoscript-lint` | TypoScript style/structure |

---

## License

GPL-2.0-or-later