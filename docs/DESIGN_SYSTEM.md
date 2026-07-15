# Atlas Noor Design System

## Concept

Atlas Noor is an Arabic RTL administrative visual system for the Local Scientific Council of Berkane. It combines deep emerald institutional surfaces, warm ivory reading areas, sandstone borders, restrained brass highlights, and geometric arch/zellij cues. Decoration frames the information hierarchy; it never competes with data or actions.

## Color roles

- Brand: deep emerald from `--color-brand-950` to `--color-brand-500`.
- Canvas: warm ivory with a faint sandstone radial light.
- Raised surfaces: opaque ivory for data-heavy pages; dark translucent forest surfaces for the navigation shell.
- Brass: active navigation, highlights, and selected states only.
- Semantic colors: blue for information, amber for warning, red for destructive actions, emerald for success.
- Bootstrap variables map to the same tokens so legacy components remain coherent.

## Typography

The primary Arabic stack is `IBM Plex Sans Arabic`, `Noto Sans Arabic`, `Tajawal`, then system sans-serif fallbacks. No remote font request is required. Headings use weight and spacing rather than extreme size. Tables, labels, values, and long descriptions retain comfortable Arabic line height.

## Spacing and shape

Spacing follows a 4 px base scale. Control height is at least 44 px. Radii range from 10 px for compact controls to 28 px for prominent panels. Directional shadows use low-opacity emerald/graphite rather than generic gray.

## Depth and surfaces

- Level 0: atmospheric canvas.
- Level 1: bordered information panels.
- Level 2: raised cards with an inner highlight.
- Level 3: sticky topbar, menus, and floating map controls.
- Level 4: modals and mobile navigation.

The login and dashboard hero use layered CSS arches and zellij geometry as static 3D scenes. Card tilt is pointer-only, capped at a subtle angle, and disabled for reduced motion and coarse pointers.

## Components

Semantic components include the application shell, sidebar groups/items, topbar, page hero/header, metric card, data panel, table toolbar, status badge, search box, filter panel, record cards, form section, sticky form actions, progress meter, empty state, alerts, modals, upload zone, pagination, role badge, and quick action.

## Motion

Motion durations are 140 ms, 220 ms, and 360 ms. Only opacity, transform, and occasionally filter are animated. Page entry, sidebar, dialogs, toasts, counters, and progress meters share one easing curve. `prefers-reduced-motion: reduce` removes non-essential animation and smooth scrolling.

## Responsive model

- Below 992 px the fixed RTL sidebar becomes an off-canvas dialog with an overlay and focus restoration.
- Data tables remain available on desktop; existing record cards become the primary mosque mobile surface.
- Toolbars wrap, actions become full-width where necessary, and map controls become a compact stacked workspace.
- Content containers use fluid gutters and never force viewport-level horizontal scrolling.

## Accessibility

The system retains semantic landmarks, a skip link, visible `:focus-visible` rings, descriptive labels, 44 px touch targets, strong contrast, accessible modal behavior through Bootstrap, Escape-close for the mobile sidebar, and focus restoration. Status always includes text or an accessible label, never color alone.

## Print

Print hides navigation, controls, dialogs, decorative scenes, and nonessential actions. It resets to white, uses black text, preserves Arabic direction and table headers, avoids row breaks, and targets A4-friendly typography.

## Tailwind and Bootstrap coexistence

Tailwind CSS 4.3.2 is compiled locally. Only Tailwind theme and utilities are imported; Preflight is omitted. The `tw` prefix produces utilities such as `tw:flex`, preventing collisions with Bootstrap. Bootstrap loads first, third-party component CSS follows, and the compiled Atlas Noor stylesheet loads last. Production/shared hosting receives only compiled files and does not require Node.js.

