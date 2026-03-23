.. _introduction:

============
Introduction
============

What does it do?
================

The **mai_accessibility** extension adds a set of backend modules to TYPO3
that analyse editorial content for common accessibility issues. Editors and
content managers can use these modules to ensure their pages meet accessibility
standards before publishing.

Accessibility checks
====================

.. list-table::
   :header-rows: 1
   :widths: 30 70

   * - Check
     - Description
   * - Alt text analysis
     - Identifies images that are missing descriptive alternative texts,
       which are required for screen reader compatibility.
   * - ARIA attributes
     - Detects missing or malformed ARIA roles and attributes on content
       elements that require them for assistive technologies.
   * - Heading structure
     - Flags incorrect or skipped heading levels (e.g. jumping from H2
       to H4) that break document outline for screen readers.
   * - Link text
     - Finds non-descriptive link texts such as "click here" or "read
       more" that do not convey meaning out of context.

Each check has its own backend module accessible from the TYPO3 backend
navigation, providing a clear list of issues and their locations.
