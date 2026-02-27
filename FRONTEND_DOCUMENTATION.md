# GAM Frontend — Module Documentation Report

> **Project:** Global Asset Management (GAM) System — Frontend SPA  
> **Stack:** Laravel 12 + Inertia.js v2 + Vue 3 (Composition API) + Tailwind CSS v4  
> **Root Path:** `C:\gam-frontend`  
> **Design System:** Glassmorphism + gradient accents + scroll-triggered animations  
> **Date:** July 2025

---

## Table of Contents

1. [Project Architecture](#1-project-architecture)
2. [Design System & Animations](#2-design-system--animations)
3. [Configuration Files](#3-configuration-files)
4. [Layout — AppLayout](#4-layout--applayout)
5. [Dashboard Module](#5-dashboard-module)
6. [Asset Browser Module](#6-asset-browser-module)
7. [Upload Module](#7-upload-module)
8. [Pipeline Module](#8-pipeline-module)
9. [Review Queue Module](#9-review-queue-module)
10. [Analytics Module](#10-analytics-module)
11. [Collections Module](#11-collections-module)
12. [Taxonomy Module](#12-taxonomy-module)
13. [Settings Module](#13-settings-module)
14. [Permissions Module](#14-permissions-module)
15. [Documents Module](#15-documents-module)
16. [Notes Module](#16-notes-module)
17. [Datasets Module](#17-datasets-module)
18. [Preview Module](#18-preview-module)
19. [Routing Reference](#19-routing-reference)
20. [Controller Reference](#20-controller-reference)

---

## 1. Project Architecture

```
gam-frontend/
├── app/Http/Controllers/
│   └── PageController.php            ← Inertia page renders (65 lines)
├── resources/
│   ├── css/app.css                   ← Tailwind v4 theme + animation system (183 lines)
│   ├── js/
│   │   ├── app.js                    ← Vue + Inertia bootstrap (17 lines)
│   │   ├── composables/
│   │   │   └── useAnimations.js      ← Scroll-reveal & count-up composable (54 lines)
│   │   ├── Layouts/
│   │   │   └── AppLayout.vue         ← Main shell with sidebar + glass header (179 lines)
│   │   └── Pages/
│   │       ├── Dashboard.vue         ← Hero banner + glass KPIs (213 lines)
│   │       ├── Assets.vue            ← Grid browser with filters (124 lines)
│   │       ├── Upload.vue            ← Drag-drop upload zone (107 lines)
│   │       ├── Pipeline.vue          ← Kanban stage cards (150 lines)
│   │       ├── Review.vue            ← Split-panel review queue (138 lines)
│   │       ├── Analytics.vue         ← Charts & KPI dashboard (169 lines)
│   │       ├── Collections.vue       ← Themed group grid (80 lines)
│   │       ├── Taxonomy.vue          ← Collapsible category tree (132 lines)
│   │       ├── Settings.vue          ← System config panels (129 lines)
│   │       ├── Permissions.vue       ← Users + permission matrix (114 lines)
│   │       ├── Documents.vue         ← Document library grid (73 lines)
│   │       ├── Notes.vue             ← Team notes feed (86 lines)
│   │       ├── Datasets.vue          ← AI training sets list (91 lines)
│   │       └── Preview.vue           ← Full asset detail view (147 lines)
│   └── views/
│       └── app.blade.php             ← Inertia root Blade template (16 lines)
├── routes/
│   └── web.php                       ← All 14 page routes (17 lines)
└── vite.config.js                    ← Vite + Vue + Tailwind build (31 lines)
```

---

## 2. Design System & Animations

### File: `resources/css/app.css` — 183 lines

This file contains the Tailwind v4 theme configuration and the complete animation library.

| Feature | Lines | Description |
|---|---|---|
| Tailwind `@theme` block | L1–L8 | Inter font family & custom CSS variables |
| `@keyframes gradient-shift` | L10–L14 | Animated background gradient (hero banners) |
| `.glass` class | L16–L21 | Glassmorphism: white/60 bg + 16px blur + 1px border |
| `.glass-dark` class | L23–L28 | Dark variant: slate-900/60 bg + 16px blur |
| Scroll entrance — `.anim-enter` | L30–L37 | Fade-up from 30px below, 0.6s ease |
| Scroll entrance — `.anim-enter-left` | L39–L43 | Slide-in from left 40px |
| Scroll entrance — `.anim-enter-right` | L45–L49 | Slide-in from right 40px |
| Scroll entrance — `.anim-enter-scale` | L51–L55 | Scale from 0.95 → 1 |
| `.visible` state | L57–L63 | Triggered by IntersectionObserver |
| `.hover-lift` | L65–L70 | Card hover: scale 1.015 + elevated shadow |
| `.btn-pulse` | L72–L78 | CTA button pulsing glow animation |
| `@keyframes pulse-glow` | L80–L84 | Glow keyframe for btn-pulse |
| `.ripple` | L86–L95 | Click ripple effect (pseudo-element) |
| `.skeleton` | L97–L106 | Shimmer loading skeleton |
| `@keyframes shimmer` | L108–L112 | Shimmer gradient animation |
| `.float` | L114–L118 | Floating decorative orb animation |
| `@keyframes float` | L120–L124 | Float keyframe (translateY oscillation) |
| `.count-up` | L126–L128 | Tabular-nums for animated counters |
| `.nav-link-sweep` | L130–L139 | Underline sweep on hover (pseudo-element) |
| Custom scrollbar | L141–L150 | Thin styled scrollbar for webkit |
| `@media prefers-reduced-motion` | L152–L163 | Disables all animations for a11y |
| `.page-enter` / `.page-leave` | L165–L183 | Page transition animation classes |

### File: `resources/js/composables/useAnimations.js` — 54 lines

| Export | Lines | Description |
|---|---|---|
| `useScrollReveal(selector)` | L3–L32 | IntersectionObserver that adds `.visible` class to elements with `.anim-enter*` when they enter viewport. Supports `data-delay` attribute for stagger. Runs on `onMounted`, disconnects on `onUnmounted`. |
| `useCountUp(target, duration, decimals)` | L34–L54 | Returns a reactive `display` ref that animates from 0 → target using ease-out-quad easing. Default duration 2000ms. |

---

## 3. Configuration Files

### `resources/views/app.blade.php` — 16 lines

| Feature | Line |
|---|---|
| Google Fonts (Inter) CDN link | L7 |
| RemixIcon 4.1.0 CDN link | L8 |
| `@vite(['resources/css/app.css', 'resources/js/app.js'])` | L11 |
| `@inertiaHead` | L12 |
| `@inertia` root element | L14 |

### `resources/js/app.js` — 17 lines

| Feature | Line |
|---|---|
| Tailwind CSS import | L1 |
| Inertia `createApp` + `resolvePageComponent` | L3–L5 |
| Vue app creation with Inertia plugin | L7–L15 |
| Dynamic page resolution via `import.meta.glob` | L10 |

### `vite.config.js` — 31 lines

| Feature | Line |
|---|---|
| `@vitejs/plugin-vue` | L2 |
| `@tailwindcss/vite` plugin | L3 |
| `laravel-vite-plugin` entry points | L9–L12 |
| `@` alias → `resources/js` | L25 |

---

## 4. Layout — AppLayout

### File: `resources/js/Layouts/AppLayout.vue` — 179 lines

| Feature | Lines | Description |
|---|---|---|
| **Template — Sidebar** | L2–L60 | Dark gradient sidebar (`from-slate-900 via-indigo-950 to-slate-900`), logo, collapsible nav sections (Overview, Assets, Pipeline, Knowledge, Admin) |
| Sidebar collapse toggle | L4–L6 | Button rotates chevron icon, toggles `sidebarOpen` ref |
| Active nav indicator | L24–L45 | Left-border pill indicator animates to active route via `isActive()` |
| Sidebar collapse animation | L2 | Width transitions `w-60` ↔ `w-16` with `transition-all duration-300` |
| **Template — Header** | L62–L90 | Glass header bar with breadcrumb, search input (focus-expand: `focus:w-72`), notification bell with dropdown |
| Notification dropdown | L78–L88 | Vue `<transition name="dropdown">` with scale/fade animation |
| **Template — Content Area** | L92–L96 | `<slot />` wrapped in `<transition name="page">` for page-level transitions |
| **Script** | L99–L140 | `sidebarOpen`, `showNotifs` refs, `navSections` data, `isActive()` URL check, `router.visit()` navigation |
| **Scoped CSS** | L142–L179 | `.scrollbar-hide`, `.fade-slide-*`, `.dropdown-*` transition classes |

---

## 5. Dashboard Module

### File: `resources/js/Pages/Dashboard.vue` — 213 lines

| Feature | Lines | Description |
|---|---|---|
| **Hero Banner** | L4–L22 | Full-width gradient banner (`from-indigo-600 via-violet-600 to-purple-700`), floating blur orbs (`.float`), greeting text, pulsing upload CTA (`btn-pulse`) |
| **KPI Cards (×4)** | L24–L43 | Glass cards with `hover-lift`, staggered scroll-reveal via `data-delay`, gradient icon backgrounds, trend indicators |
| **Review Pressure** | L45–L75 | 3 metric cards (Pending, Avg Wait, High Priority) + 3 queue items with confidence badges |
| **Pipeline Throughput** | L77–L100 | 4 horizontal bars with gradient fills, percentage labels, stage names |
| **Capacity Alert** | L102–L115 | Amber gradient card with floating icon, storage usage warning |
| **Live Feed** | L117–L140 | 5 recent events with colored ring dots, timestamps |
| **Taxonomy Breakdown** | L142–L165 | 7 taxonomy groups with emoji (hover-scale), gradient progress bars |
| Script — imports | L169 | `AppLayout`, `useScrollReveal`, `router` |
| Script — data | L173–L213 | `kpis`, `reviewQueue`, `pipelineStages`, `liveEvents`, `taxonomyGroups` arrays |

---

## 6. Asset Browser Module

### File: `resources/js/Pages/Assets.vue` — 124 lines

| Feature | Lines | Description |
|---|---|---|
| **Filter Sidebar** | L4–L27 | Sticky glass panel with checkbox filter groups (File Type, Status), tag cloud |
| **Toolbar** | L30–L44 | Category chips (All/Food/Media/Business/Location/Nature), sort dropdown, grid/list view toggle |
| **Asset Grid** | L51–L75 | 4-column grid of glass cards: gradient thumbnail, hover overlay with action buttons (eye/download/link), status badge, file details |
| **Pagination** | L78–L87 | Centered paginator with numbered buttons, active state gradient |
| Script — data | L93–L124 | `filterGroups`, `tags`, `groupChips`, `assets` array (8 items with gradient/icon/status) |

---

## 7. Upload Module

### File: `resources/js/Pages/Upload.vue` — 107 lines

| Feature | Lines | Description |
|---|---|---|
| **Drop Zone** | L8–L31 | Dashed-border area with `@dragover`/`@dragleave`/`@drop` handlers, floating decorative orbs, animated icon state change on drag, gradient upload icon, pulsing browse button |
| **Upload Queue** | L34–L54 | List of files with icon, name, progress bar (gradient fill), status badge, hover-reveal close button |
| **Metadata Form** | L57–L74 | 2-column grid: Collection select, Taxonomy select, Tags input, Source input. Gradient submit button |
| Script — refs | L79–L81 | `isDragging` reactive flag, `handleDrop` handler |
| Script — data | L83–L107 | `files` array (4 items with progress), `metaFields` array |

---

## 8. Pipeline Module

### File: `resources/js/Pages/Pipeline.vue` — 150 lines

| Feature | Lines | Description |
|---|---|---|
| **Header** | L3–L13 | Title, pipeline-active indicator (pulsing green dot), configure button |
| **Stage Cards (×6)** | L16–L58 | Horizontal scrollable kanban: Ingestion → Metadata Extraction → AI Classification → Quality Check → Review Queue → Published. Each stage card: gradient header icon, item list with type badges, progress bars, throughput footer |
| **Bottom Stats (×4)** | L61–L75 | Glass cards: Total in Pipeline, Avg Processing, Success Rate, Bottleneck |
| Script — data | L80–L150 | `stages` array (6 stages with nested items), `bottomStats` array |

---

## 9. Review Queue Module

### File: `resources/js/Pages/Review.vue` — 138 lines

| Feature | Lines | Description |
|---|---|---|
| **Header** | L3–L12 | Title, pending count badge, filter button |
| **Review Queue List** | L16–L35 | Scrollable sidebar list of reviewable assets with priority badges, colored dots, click-to-select |
| **Preview Card** | L39–L47 | Large gradient preview area with asset name overlay |
| **AI Analysis** | L50–L63 | 3-column grid of AI tags with emoji, label, confidence bar |
| **Metadata Summary** | L66–L74 | 2-column key-value grid (Camera, ISO, Aperture, etc.) |
| **Action Bar** | L77–L90 | Flag, Reject (red gradient), Edit Metadata, Approve (green gradient pulsing) buttons |
| Script — selection | L94–L96 | `selectedIdx` ref, `computed` selected item |
| Script — data | L98–L138 | 5 review items with full metadata, AI tags, meta arrays |

---

## 10. Analytics Module

### File: `resources/js/Pages/Analytics.vue` — 169 lines

| Feature | Lines | Description |
|---|---|---|
| **Header** | L3–L13 | Title, time range selector (24h/7d/30d/90d), export button |
| **KPI Row (×4)** | L16–L30 | Total Uploads, Downloads, Avg Review Time, Rejection Rate — each with trend indicator |
| **Activity Chart** | L33–L62 | Stacked bar chart (14 bars) for uploads/downloads/reviews with gradient fills |
| **Asset Types Donut** | L65–L84 | CSS `conic-gradient` donut chart with legend (Images/Videos/Documents/Vectors/Other) |
| **Top Performing Assets** | L88–L103 | Ranked list of 5 top assets with score bars |
| **Activity Feed** | L106–L117 | 5 recent events with user names and actions |
| Script — data | L121–L169 | `kpis`, `activityBars` (random-generated), `segments`, `topAssets`, `events` |

---

## 11. Collections Module

### File: `resources/js/Pages/Collections.vue` — 80 lines

| Feature | Lines | Description |
|---|---|---|
| **Header** | L3–L9 | Title, "New Collection" gradient button |
| **Featured Collection** | L12–L28 | Full-width gradient hero card with floating orbs, collection stats, "Open Collection" button |
| **Collection Grid (×9)** | L31–L50 | 3-column grid: thumbnail strip (3 color gradients), emoji + name, description, asset count, status badge, avatar circles |
| Script — data | L55–L80 | 9 collection objects with thumbs, avatars, status |

---

## 12. Taxonomy Module

### File: `resources/js/Pages/Taxonomy.vue` — 132 lines

| Feature | Lines | Description |
|---|---|---|
| **Summary Cards (×4)** | L10–L16 | Categories, AI Accuracy, Unique Tags, Unclassified |
| **Tab Bar** | L19–L25 | All Categories / AI-Managed / Manual / Unused toggles |
| **Taxonomy Tree** | L28–L58 | Collapsible categories: click header to expand/collapse (`cat.open` toggle), emoji + name + count, gradient progress bar, AI/Manual badge, nested subcategories with confidence % and edit button |
| **Slide Transition** | L126–L132 | Scoped CSS `slide-enter`/`slide-leave` for smooth collapse |
| Script — data | L66–L124 | 5 `reactive` categories with children arrays |

---

## 13. Settings Module

### File: `resources/js/Pages/Settings.vue` — 129 lines

| Feature | Lines | Description |
|---|---|---|
| **Settings Nav** | L9–L21 | Sticky sidebar with 6 sections (General, Pipeline, Integrations, Storage, Notifications, Backup) |
| **General Fields** | L24–L39 | Toggle switches (Auto-Classification, Dark Mode), dropdowns (Language, Session Timeout), text input (System Name) |
| **Pipeline Configuration** | L42–L55 | 4 range sliders: AI Confidence Threshold, Max Concurrent Jobs, Review Queue Limit, Auto-Archive Days |
| **Integrations** | L58–L70 | 6 integration cards (AWS S3, Slack, Jira, Google Drive, Adobe CC, Webhooks) with connected/available status |
| **Save Bar** | L73–L78 | Discard + Save gradient button |
| Script — data | L83–L129 | `sections`, `generalFields` (reactive toggles), `pipelineSettings`, `integrations` |

---

## 14. Permissions Module

### File: `resources/js/Pages/Permissions.vue` — 114 lines

| Feature | Lines | Description |
|---|---|---|
| **Role Cards (×4)** | L10–L22 | Super Admin, Manager, Editor, Viewer — user count + permission chips |
| **Users Table** | L25–L46 | 6 team members: avatar, name, email, role badge, last active, online dot, hover-actions |
| **Permission Matrix** | L49–L66 | Table: 9 permissions × 4 roles, green check / grey X marks |
| Script — data | L71–L114 | `roles`, `users`, `roleNames`, `permMatrix` arrays |

---

## 15. Documents Module

### File: `resources/js/Pages/Documents.vue` — 73 lines

| Feature | Lines | Description |
|---|---|---|
| **Search Bar** | L10–L15 | Glass search with document count |
| **Category Tabs** | L18–L24 | All / Guides / SOPs / Policies / References |
| **Documents Grid** | L27–L44 | 2-column grid of 8 documents: icon, title, description, author, date, status badge, tag chips |
| Script — data | L49–L73 | `docCategories`, 8 document objects |

---

## 16. Notes Module

### File: `resources/js/Pages/Notes.vue` — 86 lines

| Feature | Lines | Description |
|---|---|---|
| **Quick Compose** | L10–L23 | Avatar, textarea, formatting toolbar (@, #, attachment, emoji), post button |
| **Notes Feed** | L26–L50 | 6 notes: avatar, author, timestamp, tag badge, content text, linked asset card (optional), like/reply/share actions |
| Script — data | L55–L86 | 6 note objects with optional `asset` sub-object |

---

## 17. Datasets Module

### File: `resources/js/Pages/Datasets.vue` — 91 lines

| Feature | Lines | Description |
|---|---|---|
| **Stats Row (×4)** | L10–L18 | Total Datasets, Training Samples, Avg Accuracy, GPU Hours |
| **Dataset Cards** | L21–L46 | 5 dataset cards: icon, name, status badge, description, 4-column metrics grid, optional training progress bar, hover-reveal action buttons (view/download/delete) |
| Script — data | L51–L91 | `stats`, 5 dataset objects with metrics arrays |

---

## 18. Preview Module

### File: `resources/js/Pages/Preview.vue` — 147 lines

| Feature | Lines | Description |
|---|---|---|
| **Header** | L3–L9 | Back button, download button, share gradient button |
| **Main Preview** | L13–L23 | Large gradient preview area (h-96), zoom/fullscreen overlay buttons, dimension label, status badge |
| **Version Thumbnails** | L26–L38 | Horizontal strip of 3 versions, active ring indicator |
| **AI Tags** | L41–L52 | Flex-wrap tag cloud with confidence-based coloring (>90% indigo, >80% violet, else slate) |
| **File Information** (sidebar) | L57–L66 | Key-value pairs: filename, type, size, dimensions, DPI, camera, etc. |
| **Collections** (sidebar) | L69–L76 | 3 linked collections with emoji |
| **Activity Timeline** (sidebar) | L79–L93 | 6-item vertical timeline with connecting line, colored action dots |
| Script — data | L99–L147 | `versions`, `aiTags`, `fileInfo`, `assetCollections`, `activity` |

---

## 19. Routing Reference

### File: `routes/web.php` — 17 lines

| Route | URI | Controller Method | Line |
|---|---|---|---|
| Dashboard | `/` | `dashboard()` | L8 |
| Assets | `/assets` | `assets()` | L9 |
| Upload | `/upload` | `upload()` | L10 |
| Pipeline | `/pipeline` | `pipeline()` | L11 |
| Review | `/review` | `review()` | L12 |
| Analytics | `/analytics` | `analytics()` | L13 |
| Collections | `/collections` | `collections()` | L14 |
| Taxonomy | `/taxonomy` | `taxonomy()` | L15 |
| Settings | `/settings` | `settings()` | L16 |
| Permissions | `/permissions` | `permissions()` | L17 |
| Documents | `/documents` | `documents()` | L18 |
| Notes | `/notes` | `notes()` | L19 |
| Datasets | `/datasets` | `datasets()` | L20 |
| Preview | `/preview` | `preview()` | L21 |

---

## 20. Controller Reference

### File: `app/Http/Controllers/PageController.php` — 65 lines

Each method returns an `Inertia::render('PageName')` call.

| Method | Renders | Line |
|---|---|---|
| `dashboard()` | `Dashboard` | L10 |
| `assets()` | `Assets` | L14 |
| `upload()` | `Upload` | L18 |
| `pipeline()` | `Pipeline` | L22 |
| `review()` | `Review` | L26 |
| `analytics()` | `Analytics` | L30 |
| `collections()` | `Collections` | L34 |
| `taxonomy()` | `Taxonomy` | L38 |
| `settings()` | `Settings` | L42 |
| `permissions()` | `Permissions` | L46 |
| `documents()` | `Documents` | L50 |
| `notes()` | `Notes` | L54 |
| `datasets()` | `Datasets` | L58 |
| `preview()` | `Preview` | L62 |

---

## Quick Debug Reference

| Issue | Check File | Key Lines |
|---|---|---|
| Page not loading | `routes/web.php` | L8–L21 |
| Layout broken | `AppLayout.vue` | L2–L96 (template) |
| Animations not firing | `app.css` | L30–L63 (entrance classes) |
| IntersectionObserver not working | `useAnimations.js` | L5–L27 (observer setup) |
| Glass effect missing | `app.css` | L16–L21 (`.glass`) |
| Hover not working | `app.css` | L65–L70 (`.hover-lift`) |
| `artisan serve` failure | Use `composer run serve` | `--no-reload` flag in composer.json |
| Build errors | Run `npm run build` from `C:\gam-frontend` | Check Vite output |
