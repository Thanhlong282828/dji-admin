# Enterprise Accessibility Audit

Date: 2026-08-11. Baseline: `0cb6598`. Scope is an AA-oriented foundation, not a formal WCAG certification.

## Before redesign

The shared admin layout rendered more than 50 route links and ten disabled placeholders in one flat, continuously scrolling navigation. Section labels were visual text rather than interactive hierarchy. Mobile used a hidden checkbox/label and exposed a tall two-column link list. There was no skip link, drawer focus management, deterministic active-group behavior, menu filtering or reduced-motion rule.

Repository sampling found 99 tables, 277 form controls and 119 explicit labels. Most enterprise forms wrap controls in labels, but legacy/skeleton surfaces are inconsistent. No static `scope="col"` or captions were found in the sample; the shared frontend now adds column scope and an accessible keyboard-focusable region to existing `.table-scroll` containers. A future page-by-page content pass should add domain-specific captions and explicit help/error associations rather than generic text.

Canonical palette contrast was calculated: gold `#d7b45a` on `#080808` = 10.08:1; primary text `#f5f1e6` on `#111111` = 16.73:1; muted `#969696` on `#080808` = 6.77:1. These exceed the practical 4.5:1 normal-text target.

Existing inline raw output in generated module skeleton index pages is limited to trusted static module-name configuration; it remains technical debt and must not be reused for user content. Legacy navigation templates also contain logout `onclick` handlers outside the active enterprise admin shell.
