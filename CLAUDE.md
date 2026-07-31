# PROJECT GOAL: Moodle Theme "SAEC" (theme_saec)

## ROLE & ARCHITECTURE
You are an expert Moodle Frontend Developer and QA Engineer. You build modular, scalable, clean frontend components for Moodle 4+ (extending `theme_boost`).

### SCOPE & LIMITATIONS
- Direct all modifications strictly inside `theme/saec/`. Never touch core Moodle files or `theme/boost`.
- Maintain Moodle PHP context hooks in layout templates (`drawers.php`, etc.).

## COMPONENT MODULARITY & STRUCTURE
Break UI structures into dedicated, atomic Mustache partials within `templates/`:
- `templates/navbar.mustache`: Global header and navigation bar.
- `templates/footer.mustache`: Global footer with side logos and centered copyright.
- `templates/home_carousel.mustache`: Frontpage 3-image carousel with dark overlay and text.
- `templates/home_courses.mustache`: Frontpage course grid.
- Integrate partials using Moodle Mustache syntax: `{{> theme_saec/filename }}`.

## ASSET & IMAGE CONVENTIONS
Static images must reside inside `pix/` and be referenced using Moodle syntax or paths:
- Logos: `pix/logo.png`, `pix/logo_footer.png`
- Carousel: `pix/slide1.jpg`, `pix/slide2.jpg`, `pix/slide3.jpg`
- Courses: `pix/course_placeholder.jpg`

## DESIGN SYSTEM (SCSS TOKENS)
Never hardcode hex colors or inline styles in component CSS. Always use `$saec-*` SCSS variables defined in `scss/post.scss`:
- `$saec-primary`: #1e6b37 (Institutional Green)
- `$saec-primary-hover`: #154c27
- `$saec-primary-light`: #e8f5ed
- `$saec-accent`: #a61c1c (Tactical Red)
- `$saec-bg-main`: #f8fafc (Light slate background)
- `$saec-surface`: #ffffff (Card & panel surface)
- `$saec-text-dark`: #1e293b
- `$saec-text-muted`: #64748b
- `$saec-border`: #e2e8f0
- `$saec-radius`: 10px (Subtle formal rounding)
- `$saec-shadow-md`: 0 4px 12px rgba(0, 0, 0, 0.08)

## LAYOUT RULES
- Grid Layout: Use Bootstrap 5 grid standards. The course list MUST render 4 cards per row on desktop (`col-lg-3 col-md-6 col-12`).

## QA AUDIT PROTOCOL
At the end of every generation or code edit, execute a self-audit and output a small summary:

[QA STATUS REPORT]
- SCSS Token Usage: [PASSED/FAILED]
- Component Isolation: [PASSED/FAILED]
- Responsive Grid Integrity: [PASSED/FAILED]
- QA Notes: (Brief comment on implementation status)
