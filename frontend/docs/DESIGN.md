# FleetFlow design system — "crisp minimal"

**Direction:** a calm, dense operations console in the spirit of Linear/Vercel. Neutral zinc greys, hairline borders instead of shadows, one indigo accent for primary actions and focus, emerald reserved for "live / moving". Light and dark are tuned separately, not inverted.

## Shell

- **Canvas + panel:** the app chrome (`bg-canvas`) holds a light sidebar; page content sits in a white inset panel (`bg-bg`, 1px border, 10px radius) that scrolls on its own. On mobile the panel fills the screen and the sidebar becomes a drawer.
- **Sidebar:** workspace switcher, search button (opens ⌘K), permission-filtered navigation with quiet group labels, collapse toggle and the account menu at the bottom.
- **Topbar (48px):** breadcrumb (group › page › details), company timezone chip with a live dot, theme toggle, notifications (dot indicator).
- **Command palette (⌘K / Ctrl+K):** jump to any accessible page or run actions (new delivery, theme, sign out). Arrow keys + Enter.

## Tokens (`src/index.css`)

| Token | Light | Dark |
| --- | --- | --- |
| Canvas / panel | `#f6f6f7` / `#fff` | `#09090b` / `#0f0f11` |
| Surface / 2 / 3 | `#fff` / `#fafafa` / `#f4f4f5` | `#121214` / `#161618` / `#1c1c1f` |
| Border / strong | `#ebebed` / `#dcdce0` | `#232327` / `#303036` |
| Text fg / soft / muted / subtle | `#09090b` / `#3f3f46` / `#71717a` / `#a1a1aa` | `#fafafa` / `#d4d4d8` / `#a1a1aa` / `#6b6b74` |
| Primary (indigo) | `#4f46e5` | `#6366f1` |
| Accent (live) | `#059669` | `#34d399` |
| Status | success / warning / danger / info + `-soft` tints | |

- **Type:** Geist (UI and headings, weight 500–600, tight tracking on titles), Geist Mono for references, timestamps, axis ticks and kbd hints. Base size 14px; body copy 13px.
- **Sizing:** controls 34px (`h-8.5`), small buttons 28px, table rows 44px, cards padded 16px.
- **Radius:** 5 / 7 / 8 / 10 / 14px. **Elevation:** only popovers, dialogs and the command palette get shadows.
- **Motion:** 150–280ms fades/slide-ups; `prefers-reduced-motion` respected.

## Charts

Categorical palette `--chart-1…5` = indigo, teal, amber, pink, sky — validated with the dataviz validator for both surfaces (lightness band, chroma, CVD separation, contrast: all pass). Fixed order, never cycled; one y-axis; 2px lines; rounded bar ends; mono tick labels; legend for ≥ 2 series; hover tooltips.

## Components (`src/components/ui`)

`Button` (primary, ink, outline, secondary, ghost, danger, accent, link × sm/md/lg/icon) · `Input`/`Select`/`Textarea` · `Field` · `Card` family · `Badge` + `StatusBadge` (single status→tone map) · `StatCard` and `StatStrip` (KPI row in one hairline grid) · `Tabs` (underline) · `Segmented` · `Switch` · `KeyValue` · `DataTable` + `Pagination` · `Modal`/`ConfirmDialog`/`FormModal` · `Dropdown*` · `Tooltip` · `Avatar` (tinted initials) · `EmptyState` · `Alert` · `Skeleton` · `PageHeader`.

## Rules

- Never show invented numbers; empty data renders an `EmptyState` or "No data for this period".
- Colour carries meaning only alongside a label (status dots + text, badges).
- Text uses text tokens — never a series colour.
- Late or approximate data says so ("stale · 12 min ago", "straight-line distance").
- Every page has loading, empty and error states; render errors fall back to a recovery screen.
