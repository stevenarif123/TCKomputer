---
name: Precision Tech
colors:
  surface: '#f8f9ff'
  surface-dim: '#cbdbf5'
  surface-bright: '#f8f9ff'
  surface-container-lowest: '#ffffff'
  surface-container-low: '#eff4ff'
  surface-container: '#e5eeff'
  surface-container-high: '#dce9ff'
  surface-container-highest: '#d3e4fe'
  on-surface: '#0b1c30'
  on-surface-variant: '#45464d'
  inverse-surface: '#213145'
  inverse-on-surface: '#eaf1ff'
  outline: '#76777d'
  outline-variant: '#c6c6cd'
  surface-tint: '#565e74'
  primary: '#000000'
  on-primary: '#ffffff'
  primary-container: '#131b2e'
  on-primary-container: '#7c839b'
  inverse-primary: '#bec6e0'
  secondary: '#0058be'
  on-secondary: '#ffffff'
  secondary-container: '#2170e4'
  on-secondary-container: '#fefcff'
  tertiary: '#000000'
  on-tertiary: '#ffffff'
  tertiary-container: '#002109'
  on-tertiary-container: '#009844'
  error: '#ba1a1a'
  on-error: '#ffffff'
  error-container: '#ffdad6'
  on-error-container: '#93000a'
  primary-fixed: '#dae2fd'
  primary-fixed-dim: '#bec6e0'
  on-primary-fixed: '#131b2e'
  on-primary-fixed-variant: '#3f465c'
  secondary-fixed: '#d8e2ff'
  secondary-fixed-dim: '#adc6ff'
  on-secondary-fixed: '#001a42'
  on-secondary-fixed-variant: '#004395'
  tertiary-fixed: '#6bff8f'
  tertiary-fixed-dim: '#4ae176'
  on-tertiary-fixed: '#002109'
  on-tertiary-fixed-variant: '#005321'
  background: '#f8f9ff'
  on-background: '#0b1c30'
  surface-variant: '#d3e4fe'
typography:
  headline-xl:
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
    fontSize: 24px
    fontWeight: '600'
    lineHeight: 32px
  headline-md:
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
  body-sm:
    fontFamily: Inter
    fontSize: 14px
    fontWeight: '400'
    lineHeight: 20px
  label-md:
    fontFamily: Inter
    fontSize: 14px
    fontWeight: '600'
    lineHeight: 16px
    letterSpacing: 0.01em
  label-sm:
    fontFamily: Inter
    fontSize: 12px
    fontWeight: '500'
    lineHeight: 16px
rounded:
  sm: 0.25rem
  DEFAULT: 0.5rem
  md: 0.75rem
  lg: 1rem
  xl: 1.5rem
  full: 9999px
spacing:
  base: 4px
  xs: 8px
  sm: 16px
  md: 24px
  lg: 40px
  xl: 64px
  gutter: 24px
  margin-mobile: 16px
  margin-desktop: 48px
  max-width: 1280px
---

## Brand & Style

This design system is built for a high-performance IT and computer accessories ecosystem. The brand personality is engineered, reliable, and precise, focusing on technical expertise rather than lifestyle fluff. The target audience includes developers, sysadmins, and hardware enthusiasts who value specifications and clarity.

The visual style is **Corporate / Modern** with a heavy emphasis on **Minimalism**. It utilizes structured layouts, generous whitespace to reduce cognitive load, and a systematic approach to information density. The goal is to evoke an emotional response of professional trust and technological sophistication, ensuring the user feels they are purchasing from a specialized equipment provider.

## Colors

The palette is rooted in deep, stable tones contrasted by high-energy functional accents. 

- **Primary (#0F172A):** Used for navigation, core branding, and high-level headings to establish authority.
- **Secondary (#3B82F6):** The "Electric Blue" is reserved for primary actions, links, and highlighting active technical states.
- **Accent Green (#22C55E):** Used strictly for "Ready Stock" status and success indicators, providing a clear "go" signal.
- **Neutral / Slate:** Used for secondary text and borders to maintain a clean, organized interface.
- **Backgrounds:** Pure White is the primary canvas, with Soft Black (#1E293B) and Slate Gray variants used for sectioning and technical data tables.

## Typography

This design system utilizes **Inter** exclusively to leverage its systematic, utilitarian character. The typography is optimized for readability of technical specs and long product descriptions. 

Tight letter-spacing is applied to larger headings to maintain a modern, "engineered" look. Labels for technical specifications use a slightly heavier weight to stand out against body copy. Contrast is maintained by using Deep Blue for headings and Slate Gray for secondary body text.

## Layout & Spacing

The layout follows a **Fixed Grid** model on desktop, centering content within a 1280px container to ensure readability of technical data. 

- **Desktop:** 12-column grid with 24px gutters. Product grids typically span 3 or 4 columns.
- **Tablet:** 8-column grid with 20px gutters.
- **Mobile:** 4-column grid with 16px gutters.

Spacing follows a strict 4px/8px baseline rhythm. Horizontal margins between components are generous (LG/XL) to emphasize the minimalist aesthetic, while internal component padding remains tight (SM/MD) to feel precise.

## Elevation & Depth

Hierarchy is established through **Tonal Layers** and extremely **Ambient Shadows**. 

1. **Flat Surface:** The primary page background is Pure White.
2. **Subtle Surface:** Off-white (#F8FAFC) is used for input fields and secondary sectioning (e.g., spec tables).
3. **Elevated Cards:** Product cards use a very soft, 10% opacity Deep Blue shadow with a large blur radius (20px-30px) to appear lifted without feeling heavy.
4. **Interactive State:** On hover, cards transition to a slightly deeper shadow and a thin 1px Electric Blue border to signify focus.

## Shapes

The design system uses a **Rounded** shape language to soften the "cold" nature of technology while maintaining a professional structure. 

- **Standard Elements (Buttons, Inputs):** 8px (0.5rem) corner radius.
- **Large Elements (Cards, Modals):** 16px (1rem) corner radius.
- **Status Pills:** Fully rounded (pill-shaped) to distinguish them from interactive buttons.

## Components

### Buttons
- **Primary:** Deep Blue background, white text. Sharp, high contrast.
- **Secondary:** Electric Blue border (1px), Electric Blue text, transparent background.
- **Action:** Electric Blue background for "Add to Cart" to maximize conversion.

### Tech Cards
Product cards should have a clear vertical hierarchy: 
1. Product image on a light gray subtle background.
2. Product name in Headline-MD.
3. Key specs listed as Body-SM with icons.
4. "Ready Stock" indicator using the Accent Green pill.
5. Pricing in Headline-MD (Electric Blue).

### Input Fields
Strictly rectangular with 8px rounding. Uses a 1px Slate Gray border that shifts to Electric Blue on focus. Labels are positioned above the field using Label-MD.

### Specification Lists
Used for technical sheets. Alternate row colors between Pure White and Soft Gray for readability. Headers should use the Soft Black background with white text for a "terminal" feel.

### Iconography
Stroke-based, 2px weight, using the Primary color. Icons should be technical and literal (e.g., a realistic circuit icon for CPUs rather than a generic chip).