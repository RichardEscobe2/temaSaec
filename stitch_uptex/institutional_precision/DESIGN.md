---
name: Institutional Precision
colors:
  surface: '#f7f9ff'
  surface-dim: '#d7dae0'
  surface-bright: '#f7f9ff'
  surface-container-lowest: '#ffffff'
  surface-container-low: '#f1f4fa'
  surface-container: '#ebeef4'
  surface-container-high: '#e5e8ee'
  surface-container-highest: '#dfe3e8'
  on-surface: '#181c20'
  on-surface-variant: '#3f4940'
  inverse-surface: '#2d3135'
  inverse-on-surface: '#eef1f7'
  outline: '#6f7a6f'
  outline-variant: '#becabd'
  surface-tint: '#006d37'
  primary: '#006532'
  on-primary: '#ffffff'
  primary-container: '#0b8043'
  on-primary-container: '#d1ffd8'
  inverse-primary: '#77db93'
  secondary: '#3c6a00'
  on-secondary: '#ffffff'
  secondary-container: '#b8f47a'
  on-secondary-container: '#407100'
  tertiary: '#b00a16'
  on-tertiary: '#ffffff'
  tertiary-container: '#d42c2b'
  on-tertiary-container: '#fff1ef'
  error: '#ba1a1a'
  on-error: '#ffffff'
  error-container: '#ffdad6'
  on-error-container: '#93000a'
  primary-fixed: '#93f8ae'
  primary-fixed-dim: '#77db93'
  on-primary-fixed: '#00210c'
  on-primary-fixed-variant: '#005228'
  secondary-fixed: '#b8f47a'
  secondary-fixed-dim: '#9dd761'
  on-secondary-fixed: '#0e2000'
  on-secondary-fixed-variant: '#2c5000'
  tertiary-fixed: '#ffdad6'
  tertiary-fixed-dim: '#ffb4ac'
  on-tertiary-fixed: '#410002'
  on-tertiary-fixed-variant: '#93000d'
  background: '#f7f9ff'
  on-background: '#181c20'
  surface-variant: '#dfe3e8'
typography:
  display:
    fontFamily: Hanken Grotesk
    fontSize: 48px
    fontWeight: '700'
    lineHeight: 56px
    letterSpacing: -0.02em
  headline-lg:
    fontFamily: Hanken Grotesk
    fontSize: 32px
    fontWeight: '600'
    lineHeight: 40px
    letterSpacing: -0.01em
  headline-lg-mobile:
    fontFamily: Hanken Grotesk
    fontSize: 24px
    fontWeight: '600'
    lineHeight: 32px
  headline-md:
    fontFamily: Hanken Grotesk
    fontSize: 24px
    fontWeight: '600'
    lineHeight: 32px
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
  body-sm:
    fontFamily: Inter
    fontSize: 14px
    fontWeight: '400'
    lineHeight: 20px
  label-md:
    fontFamily: Inter
    fontSize: 12px
    fontWeight: '600'
    lineHeight: 16px
    letterSpacing: 0.05em
  button:
    fontFamily: Inter
    fontSize: 14px
    fontWeight: '600'
    lineHeight: 20px
rounded:
  sm: 0.125rem
  DEFAULT: 0.25rem
  md: 0.375rem
  lg: 0.5rem
  xl: 0.75rem
  full: 9999px
spacing:
  base: 4px
  xs: 4px
  sm: 8px
  md: 16px
  lg: 24px
  xl: 32px
  xxl: 48px
  gutter: 24px
  margin-mobile: 16px
  margin-desktop: 48px
---

## Brand & Style

This design system is built for institutional efficiency, clarity, and trust. The brand personality is professional, authoritative, and dependable, catering to high-stakes environments where data density and clear information hierarchy are paramount. 

The design style follows a **Corporate / Modern** aesthetic with a lean toward **Minimalism**. It prioritizes heavy whitespace to reduce cognitive load while utilizing high-quality typography to establish a clear reading order. The visual language is structured and systematic, ensuring that users feel grounded and in control of their workflows. Emotional responses should range from focused and calm to motivated and assured.

## Colors

The color palette is anchored by an institutional green that signals stability and growth. 

- **Primary Green (#0B8043):** Used for headers, navigation backgrounds, primary action buttons, and active interactive states.
- **Accent Light Green/Lime (#7CB342):** Utilized for positive reinforcement, such as progress bars, success indicators, and subtle hover states to provide a sense of momentum.
- **Primary Accent Red (#E53935):** Reserved for high-urgency notifications, critical deadlines, and destructive actions. It acts as a focal point in a sea of greens and neutrals.
- **Slate Neutral Gray (#5F6368):** Provides the backbone for secondary information, borders, and icon states, ensuring sufficient contrast without overwhelming the primary palette.
- **Background Tiers:** A clean white (#FFFFFF) is used for elevated cards and content containers, while a soft slate (#F8FAFC) provides the foundation for the application shell.

## Typography

This design system utilizes a dual-font approach to balance character with utility. **Hanken Grotesk** is used for headlines to provide a sharp, contemporary edge that feels modern yet institutional. **Inter** is utilized for body copy and labels due to its exceptional legibility at small sizes and its neutral, systematic nature.

For mobile layouts, large headlines scale down to ensure they remain within the viewport without breaking word blocks. All labels and buttons use a slightly heavier weight to ensure clear affordance against background colors.

## Layout & Spacing

The layout is built on a **Fluid Grid** system with a focus on logical containment. 

- **Desktop:** A 12-column grid with 24px gutters and 48px outer margins. Content should be grouped in logical "blocks" that span 3, 4, 6, or 12 columns.
- **Tablet:** An 8-column grid with 16px gutters and 24px margins.
- **Mobile:** A 4-column grid with 16px gutters and 16px margins.

The spacing rhythm follows a 4px baseline, ensuring that every element—from the height of a button to the padding within a card—is a multiple of 4. This mathematical consistency reinforces the "Institutional" personality of the system.

## Elevation & Depth

This design system uses **Tonal Layers** and **Low-contrast outlines** to define hierarchy. 

Depth is achieved primarily through color shifts (White cards on a Soft Slate background) rather than heavy shadows. When shadows are required for transient elements like dropdowns or modals, use a very soft, diffused shadow: `0 4px 12px rgba(95, 99, 104, 0.1)`. 

Borders are used sparingly and should be 1px wide using the Slate Neutral Gray (#5F6368) at 20% opacity. This creates a clean, "ghost border" effect that defines structure without cluttering the interface.

## Shapes

The shape language is **Soft**. A subtle 0.25rem (4px) corner radius is applied to most standard components (buttons, inputs, cards) to humanize the interface while maintaining its professional posture. Large containers or featured cards may use `rounded-lg` (8px) to draw the eye, but sharp edges are strictly avoided to prevent the UI from feeling aggressive.

## Components

- **Buttons:** Primary buttons use a solid Primary Green (#0B8043) with white text. Secondary buttons use a transparent background with a Primary Green border and text. Success actions use Accent Light Green (#7CB342).
- **Cards:** Pure white (#FFFFFF) background with a 1px border (#5F6368 at 15% opacity). No shadow by default; a soft shadow appears only on hover or interaction.
- **Input Fields:** 4px rounded corners, 1px border in Slate Gray. Active state uses a 2px Primary Green border. Error states use Primary Accent Red (#E53935).
- **Chips & Tags:** Small, low-height elements with a light tint of the primary color and dark text. Success tags use the Accent Light Green.
- **Progress Bars:** Use a Slate Gray track with an Accent Light Green (#7CB342) fill to represent positive completion.
- **Lists:** Clean rows with 16px vertical padding, separated by a thin light-gray divider. Use Primary Green for icons or active list items.