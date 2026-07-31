---
name: Academic Excellence System
colors:
  surface: '#f9f9ff'
  surface-dim: '#cfdaf2'
  surface-bright: '#f9f9ff'
  surface-container-lowest: '#ffffff'
  surface-container-low: '#f0f3ff'
  surface-container: '#e7eeff'
  surface-container-high: '#dee8ff'
  surface-container-highest: '#d8e3fb'
  on-surface: '#111c2d'
  on-surface-variant: '#404942'
  inverse-surface: '#263143'
  inverse-on-surface: '#ecf1ff'
  outline: '#707971'
  outline-variant: '#c0c9bf'
  surface-tint: '#2e6a46'
  primary: '#00341b'
  on-primary: '#ffffff'
  primary-container: '#0b4d2c'
  on-primary-container: '#80bd93'
  inverse-primary: '#96d5a9'
  secondary: '#006c49'
  on-secondary: '#ffffff'
  secondary-container: '#6cf8bb'
  on-secondary-container: '#00714d'
  tertiary: '#422700'
  on-tertiary: '#ffffff'
  tertiary-container: '#603b00'
  on-tertiary-container: '#f49d09'
  error: '#ba1a1a'
  on-error: '#ffffff'
  error-container: '#ffdad6'
  on-error-container: '#93000a'
  primary-fixed: '#b1f1c3'
  primary-fixed-dim: '#96d5a9'
  on-primary-fixed: '#00210f'
  on-primary-fixed-variant: '#115130'
  secondary-fixed: '#6ffbbe'
  secondary-fixed-dim: '#4edea3'
  on-secondary-fixed: '#002113'
  on-secondary-fixed-variant: '#005236'
  tertiary-fixed: '#ffddb8'
  tertiary-fixed-dim: '#ffb95f'
  on-tertiary-fixed: '#2a1700'
  on-tertiary-fixed-variant: '#653e00'
  background: '#f9f9ff'
  on-background: '#111c2d'
  surface-variant: '#d8e3fb'
typography:
  display-lg:
    fontFamily: Inter
    fontSize: 48px
    fontWeight: '700'
    lineHeight: 56px
    letterSpacing: -0.02em
  headline-lg:
    fontFamily: Inter
    fontSize: 32px
    fontWeight: '600'
    lineHeight: 40px
    letterSpacing: -0.01em
  headline-lg-mobile:
    fontFamily: Inter
    fontSize: 28px
    fontWeight: '600'
    lineHeight: 36px
  title-md:
    fontFamily: Inter
    fontSize: 20px
    fontWeight: '600'
    lineHeight: 28px
  body-lg:
    fontFamily: Inter
    fontSize: 18px
    fontWeight: '400'
    lineHeight: 28px
  body-md:
    fontFamily: Inter
    fontSize: 16px
    fontWeight: '400'
    lineHeight: 24px
  label-md:
    fontFamily: Inter
    fontSize: 14px
    fontWeight: '500'
    lineHeight: 20px
    letterSpacing: 0.01em
  label-sm:
    fontFamily: Inter
    fontSize: 12px
    fontWeight: '600'
    lineHeight: 16px
rounded:
  sm: 0.125rem
  DEFAULT: 0.25rem
  md: 0.375rem
  lg: 0.5rem
  xl: 0.75rem
  full: 9999px
spacing:
  container-max: 1280px
  gutter: 1.5rem
  margin-mobile: 1rem
  margin-desktop: 2.5rem
  stack-sm: 0.5rem
  stack-md: 1rem
  stack-lg: 2rem
---

## Brand & Style

The design system is engineered for an institutional, professional, and trustworthy micro-credentials platform. It balances the prestige of traditional academia with the agility of modern digital learning. 

The aesthetic is **Corporate / Modern**, characterized by:
- **Clarity & Authority:** Systematic layouts that prioritize information hierarchy and legibility.
- **Academic Trust:** A rich, forest-green primary palette that evokes growth and tradition.
- **Digital Precision:** Sharp borders combined with subtle, high-quality depth effects to signify a contemporary, tech-forward platform.

The target audience consists of students seeking professional advancement and faculty managing digital certifications. The UI must feel reliable, organized, and motivating.

## Colors

The palette is anchored in **Deep Forest Green**, symbolizing the university's institutional identity and stability. 

- **Primary (#0B4D2C):** Used for navigation bars, primary buttons, and heavy headings to establish authority.
- **Secondary (#10B981):** A vibrant accent for progress indicators, success states, and interactive hover effects.
- **Highlight (#F59E0B):** Reserved exclusively for micro-credentials, badges, and "achieved" states to create a clear visual reward system.
- **Neutrals:** **Charcoal (#1E293B)** provides high-contrast legibility for body text, while **Warm Light Gray (#F8FAFC)** serves as the primary canvas background to reduce eye strain.

## Typography

This design system utilizes **Inter** for its exceptional legibility and neutral, professional tone across all interface levels.

- **Scale:** A tight typographic scale is used to maintain an information-dense yet organized look suitable for course catalogs and dashboards.
- **Weights:** Use **Semi-Bold (600)** and **Bold (700)** for semantic hierarchy in headlines. **Medium (500)** is reserved for interactive elements like buttons and navigation links.
- **Responsive:** On mobile devices, `display` and `headline-lg` sizes scale down slightly to ensure headers do not push critical content off-screen.

## Layout & Spacing

The layout follows a **Fixed Grid** philosophy for desktop to maintain the structured feel of a formal institution, while transitioning to a fluid model for mobile.

- **Grid:** 12-column system for desktop (1280px max-width) with 24px (1.5rem) gutters.
- **Rhythm:** An 8px base unit drives all spacing. Elements are stacked using `stack-md` (16px) for related content and `stack-lg` (32px) to separate sections.
- **Margins:** generous outer margins ensure content remains centered and readable on wide displays.

## Elevation & Depth

To maintain a clean, institutional look, this design system uses **Tonal Layers** combined with **Ambient Shadows**.

- **Surfaces:** The base background is `#F8FAFC`. Content lives on White (`#FFFFFF`) cards to create immediate separation.
- **Shadows:** Use a single, soft shadow style for interactive cards: `0 4px 6px -1px rgba(11, 77, 44, 0.05), 0 2px 4px -2px rgba(11, 77, 44, 0.1)`. The shadow color is slightly tinted with the primary green to feel more organic.
- **Borders:** Cards use a subtle `1px` solid border in `#E2E8F0` to ensure crispness, especially on low-contrast screens.

## Shapes

The shape language is **Soft (0.25rem)**. This slight rounding takes the "edge" off the formal layout without becoming too casual or playful.

- **Components:** Buttons and input fields use the base `rounded` (4px).
- **Cards:** Course cards and modal containers use `rounded-lg` (8px) to soften the large surface areas.
- **Badges:** Use a higher roundedness or pill-shape to distinguish them from functional UI buttons.

## Components

### Buttons
- **Primary:** Solid `#0B4D2C` background with white text. High-contrast and authoritative.
- **Secondary:** Outline `#0B4D2C` with 1px border. Used for tertiary actions.
- **Ghost:** No background, `#10B981` text. Used for "View Details" or navigation within cards.

### Cards
- White background, 1px `#E2E8F0` border, and the ambient green-tinted shadow. 
- Header areas within cards can use a light gray top-bar to group metadata.

### Credentials & Badges
- **Micro-credentials:** Must feature the **Warm Gold (#F59E0B)** color. Use a pill-shape with `label-sm` bold typography.
- **Status Chips:** Use secondary green for "Completed" and charcoal for "Draft" or "In Progress."

### Input Fields
- White background with a `#CBD5E1` border. On focus, the border shifts to the primary `#0B4D2C` with a 2px outer glow.

### Lists
- Use structured data lists with subtle horizontal dividers (`1px solid #F1F5F9`). Icons should be used sparingly, colored in the secondary green to draw the eye to key information points.