.. _installation:

============
Installation
============

Requirements
============

* TYPO3 13.4 LTS
* PHP 8.2 or later
* No additional PHP extensions required

Composer installation
=====================

.. code-block:: bash

   composer require maispace/mai-accessibility

TYPO3 will automatically discover the extension. No manual activation is
required.

First steps
===========

1. Navigate to the TYPO3 backend after installation.
2. The accessibility modules are available in the backend navigation under
   the **Accessibility** section.
3. Select a module (e.g. *Alt Text*, *ARIA Attributes*, *Heading Structure*,
   or *Link Text*) to run the corresponding analysis on your content.
4. Review the reported issues and update your content elements accordingly.

No database schema changes or TypoScript inclusion is required for basic
operation. All modules register automatically via the extension configuration.
