---
name: Mail Indexer and Searcher
description: A dark-first, self-hosted email client for focused personal mail retrieval.
colors:
  zinc-50: "#fafafa"
  zinc-100: "#f5f5f5"
  zinc-200: "#e5e5e5"
  zinc-300: "#d4d4d4"
  zinc-400: "#a3a3a3"
  zinc-500: "#737373"
  zinc-600: "#525252"
  zinc-700: "#404040"
  zinc-800: "#262626"
  zinc-900: "#171717"
  zinc-950: "#0a0a0a"
typography:
  body:
    fontFamily: "Instrument Sans, ui-sans-serif, system-ui, sans-serif"
    fontWeight: 400
  label:
    fontFamily: "Instrument Sans, ui-sans-serif, system-ui, sans-serif"
    fontWeight: 500
rounded:
  md: "6px"
  xl: "12px"
  2xl: "16px"
spacing:
  2: "8px"
  3: "12px"
  4: "16px"
  5: "20px"
  6: "24px"
components:
  input-search:
    backgroundColor: "{colors.zinc-900}"
    textColor: "{colors.zinc-50}"
    rounded: "{rounded.xl}"
  email-list:
    backgroundColor: "{colors.zinc-900}"
    rounded: "{rounded.2xl}"
  email-list-item:
    backgroundColor: "{colors.zinc-900}"
    padding: "16px 20px"
---

# Design System: Mail Indexer and Searcher

## Overview

**Creative North Star: "Midnight Workspace"**

The system is a dark-first workspace for an individual developer moving quickly through personal mail. Its quiet zinc surfaces and compact, familiar controls put attention on the message list, search state, and reading task—not decoration.

The visual language is deliberately developer-oriented: dense enough for sustained use, but ordered enough to scan sender, subject, folder, attachments, and time at a glance. It has no brand assets or expressive palette to preserve; contrast, spacing, and state changes carry hierarchy.

**Key Characteristics:**
- Required dark appearance with a near-black workspace and graphite surface hierarchy.
- Compact information density with a dependable 4px-derived spacing rhythm.
- Border-led grouping, with restrained tonal elevation for active and interactive states.
- Instrument Sans typography with weight and scale—not display treatments—creating hierarchy.

## Colors

**Nocturne Zinc** is a neutral grayscale ramp: light values support text and focus, while deep graphite values establish the dark workspace.

### Primary
- **Near-White Signal** (`zinc-50`): High-contrast foreground and the light-side endpoint of the system.

### Neutral
- **Pale Zinc** (`zinc-100` through `zinc-300`): Light-mode surfaces and understated dividers where required.
- **Quiet Zinc** (`zinc-400` through `zinc-600`): Secondary text and supporting metadata.
- **Graphite Zinc** (`zinc-700` through `zinc-800`): Borders, selected-state contrast, and raised dark surfaces.
- **Midnight Zinc** (`zinc-900` through `zinc-950`): Primary dark surfaces and the deepest page backdrop.

### Named Rules
**The Dark-First Rule.** Dark mode is the required default; light styles are compatibility states, not the product's primary visual experience.

**The Tonal Signal Rule.** Use the zinc ramp to distinguish structure and interaction. Do not introduce decorative color as a substitute for clear hierarchy.

## Typography

**Body Font:** Instrument Sans (with `ui-sans-serif`, `system-ui`, and `sans-serif` fallbacks)

**Character:** A compact sans-serif voice that remains highly legible in dense lists, form labels, metadata, and long email previews. Weight and restrained size changes provide hierarchy.

### Hierarchy
- **Title** (semibold, large Flux heading): Page and empty-state titles.
- **Body** (regular, 14–16px): Email previews, form help, and readable supporting copy.
- **Label** (medium, 14px): Form labels, sender names, and compact controls.
- **Metadata** (regular, 12px): Folder tags, attachment counts, and timestamps.

### Named Rules
**The Scan-First Rule.** Sender and subject carry the strongest type weight in email rows; preview text and metadata must remain visually subordinate.

## Layout

The application uses a responsive application shell with a sticky, collapsible sidebar. Content stacks vertically with 24px section gaps; search and folder controls become a two-column layout only at large widths, preserving a single-column workflow on smaller screens. Email rows use compact internal padding (16px vertical, 20px horizontal) and stack their metadata on narrow displays.

## Elevation & Depth

Depth is subtly layered rather than shadow-heavy. Near-black page and sidebar surfaces establish the base plane; cards and input controls lift through `zinc-900` backgrounds, `zinc-800` borders, and hover fills. The only observed shadow is the low `shadow-sm` treatment on the native folder selector, so borders and tonal contrast remain the primary structural tools.

### Named Rules
**The Border Before Shadow Rule.** Separate workspace regions with a zinc border and surface shift first; use shadows only when an interactive control needs a small tactile lift.

## Shapes

The form language is gently rounded and compact: standard controls use soft corners (6px), fields and alerts use 12px corners, and email-list containers use 16px corners. Pills are reserved for compact metadata such as folders and attachment counts. Borders are thin and low-contrast, never heavy outlines.

## Components

### Buttons

Dense and developer-oriented controls use Flux's restrained component styling.

- **Shape:** Softly rounded controls (6px) with compact labels or familiar icons.
- **Primary:** A light-on-dark or dark-on-light high-contrast action treatment using the existing Flux accent token.
- **Hover / Focus:** Quick tonal transitions; focused fields use a 2px accent ring with a matching offset.

### Cards / Containers

- **Corner Style:** Email collections are softly contained (16px); alerts and fields are gently rounded (12px).
- **Background:** Raised dark containers use `zinc-900`.
- **Border:** A `zinc-800` border contains dark list surfaces.
- **Internal Padding:** Use the existing 16px and 20px row rhythm.

### Inputs / Fields

- **Style:** Dark raised surface, thin zinc border, and 12px corner radius.
- **Focus:** A 2px accent ring plus offset; never rely on placeholder text alone for labels.

### Chips

- **Style:** Compact full-round folder and attachment metadata labels use a muted zinc background; attachment chips add a low-contrast border.
- **State:** Chips communicate metadata, not primary navigation or decoration.

### Navigation

The collapsible Flux sidebar and mobile header keep top-level navigation compact. An icon and short label identify each destination; the current route receives the component library's active treatment. The sidebar remains the durable desktop navigation model, while the header exposes the same navigation on mobile.

## Do's and Don'ts

### Do:
- **Do** default every new surface to the dark `zinc-900`/`zinc-950` workspace family.
- **Do** preserve the sender → subject → preview → metadata reading order in message lists.
- **Do** use the established 8px, 12px, 16px, 20px, and 24px spacing increments.
- **Do** use a tonal hover or focus state on interactive rows and controls.

### Don't:
- **Don't** add brand colors, gradients, or decorative imagery to compensate for missing hierarchy.
- **Don't** use large display typography or oversized controls in operational mail views.
- **Don't** use pronounced card shadows where a zinc border and surface shift communicate containment.
- **Don't** treat light mode as the primary design target.
