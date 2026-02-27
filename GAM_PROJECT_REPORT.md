# GAM — Global Asset Management System
## Comprehensive Project Report

---

## 1. Project Overview

**GAM (Global Asset Management)** is a full-stack enterprise digital asset management system designed for ingesting, classifying, reviewing, and distributing visual and document assets at scale. The system features AI-powered automatic tagging using computer vision, a controlled taxonomy of 945+ synonym rules across 15 classification groups, role-based access control, version management, and integrations with Google Drive, BookStack, Trilium Notes, and CKAN.

- **Total Lines of Code (approx.):** ~15,000+ across backend and frontend
- **Total Pages / Views:** 18 distinct pages
- **Total Routes:** ~80 web routes + 5 REST API endpoints
- **Total Models:** 9 Eloquent models
- **Total Jobs:** 5 queue jobs (async pipeline)
- **Docker Services:** 10 containers

---

## 2. Tech Stack

### 2.1 Backend

| Technology | Version | Purpose |
|---|---|---|
| **PHP** | 8.4 | Server-side language |
| **Laravel** | 12.0 | MVC framework |
| **Inertia.js (Server)** | 2.0 | Server-driven SPA bridge |
| **Laravel Sanctum** | 4.3 | API token authentication |
| **Laravel Horizon** | 5.45 | Redis queue dashboard |
| **Laravel Scout** | 10.24 | Full-text search abstraction |
| **Spatie Permission** | 7.2 | Role-based access control (RBAC) |
| **Spatie Activity Log** | 4.12 | Audit trail logging |
| **OpenAI PHP (Laravel)** | 0.18.0 | AI model integration |
| **MySQL** | 8.0 | Primary relational database |
| **Redis** | 7 | Queue driver, cache, sessions |
| **Meilisearch** | 1.12 | Full-text search engine |

### 2.2 Frontend

| Technology | Version | Purpose |
|---|---|---|
| **Vue.js** | 3.5 | Reactive UI framework (Composition API) |
| **Inertia.js (Client)** | 2.3 | SPA without client-side routing |
| **Tailwind CSS** | 4.2 | Utility-first CSS framework |
| **Vite** | 7.0 | Build tool + HMR dev server |
| **ApexCharts** | 5.6 | Data visualisation charts |
| **Lucide Vue** | 0.575 | Icon library (secondary) |
| **Remix Icons** | CDN | Primary icon set (`ri-*` classes) |

### 2.3 Infrastructure / DevOps

| Technology | Purpose |
|---|---|
| **Docker + Docker Compose** | Multi-container orchestration (10 services) |
| **Nginx** | Reverse proxy (inside app container) |
| **Supervisor** | Process manager (PHP-FPM + Nginx + Cron) |
| **Ghostscript** | EPS/PDF raster rendering |
| **ImageMagick** | PSD/TIFF image processing |
| **Inkscape** | SVG/AI vector conversion |
| **Poppler** | PDF page extraction (pdftoppm) |
| **FFmpeg** | Video thumbnail extraction |
| **LibreOffice** | DOCX/XLSX → PDF headless conversion |

### 2.4 External Integrations

| Service | Purpose |
|---|---|
| **OpenRouter API** | AI model gateway (routes to GPT-4o-mini) |
| **Google Drive** | OAuth 2.0 file import |
| **BookStack** | Document/knowledge base CMS |
| **Trilium Notes** | Team collaboration notes |
| **CKAN** | Open data catalogue for datasets |
| **Soketi** | Self-hosted WebSocket server (Pusher-compatible) |

---

## 3. Architecture

### 3.1 System Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                        Docker Network                        │
│                                                              │
│  ┌─────────────┐  ┌─────────┐  ┌────────────┐              │
│  │  gam-app    │  │  Redis  │  │ Meilisearch│              │
│  │ PHP 8.4-FPM │──│  Queue  │  │   Search   │              │
│  │ + Nginx     │  │  Cache  │  │   Engine   │              │
│  │ + CLI Tools │  │ Session │  └────────────┘              │
│  └──────┬──────┘  └─────────┘                               │
│         │                                                    │
│  ┌──────┴──────┐  ┌──────────┐  ┌──────────┐               │
│  │ gam-horizon │  │ MySQL 8  │  │ Soketi WS│               │
│  │ Queue Worker│  │ Database │  │ Realtime  │               │
│  └─────────────┘  └──────────┘  └──────────┘               │
│                                                              │
│  ┌──────────┐  ┌──────────┐  ┌──────┐  ┌─────────┐        │
│  │BookStack │  │ Trilium  │  │ CKAN │  │CKAN-Solr│        │
│  │  Docs    │  │  Notes   │  │ Data │  │ Search  │        │
│  └──────────┘  └──────────┘  └──────┘  └─────────┘        │
└─────────────────────────────────────────────────────────────┘
```

### 3.2 Data Flow — Asset Processing Pipeline

```
Upload/Import ──► Staging ──► ProcessAssetPipeline Job
                                    │
                    ┌───────────────┼────────────────┐
                    ▼               ▼                ▼
              1. Hashing      2. Preview Gen    3. AI Tagging
              (SHA-256)       (GD/FFmpeg/etc)   (GPT-4o-mini)
                    │               │                │
                    └───────────────┼────────────────┘
                                    ▼
                            4. Taxonomy Validation
                            (945 synonym rules)
                                    │
                                    ▼
                            5. Indexing (Meilisearch)
                                    │
                                    ▼
                            6. Move to Production
                                    │
                                    ▼
                            Review Queue ──► Approved / Rejected
```

---

## 4. Features — Detailed Breakdown

---

### 4.1 Dashboard (Command Center)

**Page:** `Dashboard.vue` (~260 lines)  
**Controller:** `PageController::dashboard()` (~100 lines)

Real-time KPI command center showing system health, pipeline throughput, and review queue status.

**Sub-features:**
- 4 KPI summary cards (total assets, AI classified %, preview completion %, storage gauge)
- SVG donut chart — taxonomy group distribution
- 7-day upload trend bar chart
- Pipeline stage progress bars (7 stages: Queued → Complete)
- Review pressure queue with top-3 pending assets
- Live activity feed (last 5 actions)
- Service health indicators (MySQL, Redis, Meilisearch, BookStack, Trilium, CKAN)

**Example — Dashboard KPI data assembly (PHP):**
```php
return Inertia::render('Dashboard', [
    'totalAssets'      => $totalAssets,
    'aiClassified'     => (clone $assetsQuery)->whereNotNull('group_classification')->count(),
    'aiClassifiedPct'  => $totalAssets > 0
        ? round((clone $assetsQuery)->whereNotNull('group_classification')->count() / $totalAssets * 100, 1)
        : 0,
    'storageUsedBytes' => Asset::sum('file_size'),
    'pipelineStages'   => [
        ['stage' => 'Ingestion',  'count' => Asset::where('pipeline_status', 'queued')->count()],
        ['stage' => 'AI Tagging', 'count' => Asset::where('pipeline_status', 'tagging')->count()],
        ['stage' => 'Complete',   'count' => Asset::where('pipeline_status', 'done')->count()],
    ],
    'serviceHealth'    => $this->checkServiceHealth(),
]);
```

**Example — SVG donut chart (Vue):**
```vue
<svg viewBox="0 0 120 120" class="w-40 h-40">
  <circle v-for="seg in donutSegments" :key="seg.name"
    cx="60" cy="60" r="50" fill="none" stroke-width="18"
    :stroke="seg.color"
    :stroke-dasharray="seg.dash"
    :stroke-dashoffset="seg.offset" />
</svg>
```

---

### 4.2 Asset Browser

**Page:** `Assets.vue` (~270 lines)  
**Controller:** `PageController::assets()` (~100 lines)

Full-featured asset browser with filtering, sorting, grid/list views, bulk operations, and thumbnail previews.

**Sub-features:**
- Filter sidebar: file type, pipeline status, review status, tags, date range
- Group classification chips
- Sort dropdown (newest, oldest, name, size)
- Grid / list view toggle with scroll-reveal animations
- Thumbnail previews via `/serve/thumbnail/{id}` with gradient+icon fallbacks
- Hover overlay actions (preview, download, copy link)
- Admin multi-select with floating bulk-delete bar
- Inertia-driven server-side pagination

**Example — Grid view with thumbnail (Vue):**
```vue
<div class="aspect-[4/3] bg-gradient-to-br from-slate-100 to-slate-50 relative overflow-hidden">
  <img v-if="asset.thumbnailUrl"
    :src="asset.thumbnailUrl" :alt="asset.name"
    class="w-full h-full object-cover"
    @error="(e) => e.target.style.display = 'none'" />
  <div class="absolute inset-0 bg-gradient-to-t from-black/40 to-transparent">
    <span class="text-white text-lg">
      <i :class="extensionIcon(asset.ext)"></i>
    </span>
  </div>
</div>
```

**Example — RBAC-scoped asset query (PHP):**
```php
$baseQuery = Asset::forUser($user)->where('pipeline_status', 'done');

// scopeForUser in Asset model:
public function scopeForUser($query, User $user)
{
    if ($user->hasRole('Admin')) return $query;
    $groups = [];
    if ($user->hasRole('Food Team'))      $groups[] = 'Food';
    if ($user->hasRole('Media Team'))     $groups[] = 'Media';
    if ($user->hasRole('Marketing Team')) { $groups[] = 'Business'; $groups[] = 'Lifestyle'; }
    return $query->whereIn('group_classification', $groups);
}
```

---

### 4.3 Upload Center

**Page:** `Upload.vue` (~802 lines — largest page)  
**Controller:** `ActionController::uploadStore()` / `uploadSingle()` / `uploadStatus()`

Drag-and-drop file upload with concurrent processing, real-time pipeline polling, inline AI metadata editing, and duplicate detection.

**Sub-features:**
- Drag-and-drop zone with file type validation
- Concurrent upload workers (3 simultaneous)
- Per-file progress bars and status indicators
- Pipeline status live polling (every 2 seconds)
- Auto-expanding AI metadata cards (shows group, tags, description when ready)
- Inline metadata editing (override AI group, add/remove tags)
- Duplicate detection via SHA-256 hash with "Replace Master" option
- Batch defaults (assign collection, pre-select group)
- Multi-select cancel bar

**Example — File upload with dedup & pipeline dispatch (PHP):**
```php
$request->validate([
    'files'   => 'required|array|min:1',
    'files.*' => 'required|file|max:512000', // 500 MB per file
]);

foreach ($request->file('files') as $file) {
    $hash = hash_file('sha256', $file->getRealPath());
    $existing = Asset::where('sha256_hash', $hash)->first();
    if ($existing) {
        $duplicates[] = ['filename' => $file->getClientOriginalName(), 'existingId' => $existing->id];
        continue;
    }
    // Store to staging, create Asset, dispatch pipeline
    ProcessAssetPipeline::dispatch($asset);
}
```

**Example — Pipeline polling in Upload.vue (Vue):**
```vue
const startStatusPolling = (entry) => {
  const timer = setInterval(async () => {
    const res = await axios.get(`/upload/status/${entry.assetId}`);
    entry.pipelineStatus = res.data.pipeline_status;
    if (res.data.ai_metadata && !entry.aiMetadata) {
      entry.aiMetadata = res.data.ai_metadata;
      entry.editForm.group = res.data.ai_metadata.group || '';
      entry.expanded = true;             // Auto-open metadata card
    }
    if (['done', 'failed', 'cancelled'].includes(entry.pipelineStatus)) {
      clearInterval(timer);
    }
  }, 2000);
};
```

---

### 4.4 AI Tagging System

**Job:** `AiTagAsset.php` (~500 lines)  
**Service:** `TaxonomyService.php` (~350 lines)  
**Model:** GPT-4o-mini via OpenRouter API

Computer vision-based automatic asset classification using a controlled taxonomy of 7 visual groups + 8 document groups with 945+ synonym rules.

**Sub-features:**
- Vision analysis: Base64 image sent to GPT-4o-mini with structured taxonomy prompt
- Text-only fallback: Classification from filename/extension when vision unavailable
- Extension-based fallback: Deterministic group mapping when API fails
- Structured JSON output: tags (label + confidence), group classification, description
- Synonym normalization: Raw AI terms mapped to canonical terms via TaxonomyRule table
- Confidence scoring: 0.0–1.0 scale, threshold-based auto-approval
- `<think>` tag stripping for reasoning models (Qwen3/DeepSeek-R1)

**Example — Vision request to OpenRouter (PHP):**
```php
$httpResponse = Http::timeout($timeout)
    ->withHeaders([
        'Authorization' => "Bearer {$apiKey}",
        'HTTP-Referer'  => config('app.url'),
    ])
    ->post("{$baseUrl}/chat/completions", [
        'model'    => $model,       // openai/gpt-4o-mini
        'messages' => [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user',   'content' => [
                ['type' => 'image_url', 'image_url' => [
                    'url' => "data:{$dataMime};base64,{$base64Image}"
                ]],
                ['type' => 'text', 'text' => "Classify this image..."],
            ]],
        ],
        'provider' => ['require_parameters' => true],
    ]);
```

**Example — Taxonomy system prompt generation (PHP):**
```php
public function buildSystemPrompt(): string
{
    $groups  = config('gam.groups');
    $compact = collect($groups)->map(function ($desc, $code) {
        $keywords = TaxonomyRule::where('group_hint', $code)
            ->where('is_active', true)
            ->pluck('canonical_term')
            ->unique()->take(40)->implode(', ');
        return "{$code}: {$desc}\n  Keywords: {$keywords}";
    })->implode("\n\n");

    return "You are GAM, a digital asset classifier.\n\n" .
           "TAXONOMY GROUPS:\n{$compact}\n\n" .
           "Return JSON: {\"group\": \"CODE\", \"tags\": [...], ...}";
}
```

---

### 4.5 Asset Processing Pipeline

**Job:** `ProcessAssetPipeline.php` (~300 lines)

6-stage orchestration pipeline that processes uploaded assets from staging to production.

**Stages:**
1. **Hashing** — SHA-256 integrity verification
2. **Preview Generation** — Thumbnails (300px) + medium previews (1200px)
3. **AI Tagging** — GPT-4o-mini vision classification
4. **Taxonomy Validation** — Synonym normalization, fuzzy matching, group voting
5. **Indexing** — Meilisearch full-text indexing
6. **Production Move** — Staging disk → assets disk (`processed/Y/m/d/`)

**Example — Pipeline orchestration (PHP):**
```php
public function handle(): void
{
    $asset = $this->asset;
    $stages = ['hashing', 'previewing', 'tagging', 'classifying', 'indexing', 'done'];

    foreach ($stages as $stage) {
        $asset->refresh();
        if ($asset->pipeline_status === 'cancelled') {
            throw new PipelineCancelledException();
        }
        $asset->update(['pipeline_status' => $stage]);
        match ($stage) {
            'hashing'     => $this->computeHash($asset),
            'previewing'  => GeneratePreview::dispatchSync($asset),
            'tagging'     => AiTagAsset::dispatchSync($asset),
            'classifying' => ApplyTaxonomy::dispatchSync($asset),
            'indexing'    => null, // Meilisearch deferred
            'done'        => $this->moveToProduction($asset),
        };
    }
}
```

---

### 4.6 Preview Generation

**Job:** `GeneratePreview.php` (~400 lines)

Multi-format preview generation with format-specific handler routing and graceful multi-tool fallback chains.

**Supported Formats:**

| Format | Processing Tool |
|---|---|
| JPG, PNG, GIF, WebP, BMP | PHP GD (built-in) |
| PDF | pdftoppm → Ghostscript → ImageMagick |
| PSD, TIFF | ImageMagick |
| SVG | Inkscape → ImageMagick |
| AI, EPS | Inkscape → Ghostscript |
| MP4, MOV, AVI | FFmpeg (frame at 1 second) |
| DOCX, XLSX | LibreOffice headless → PDF → PNG |

**Example — Format handler routing (PHP):**
```php
private const FORMAT_HANDLERS = [
    'jpg'  => 'handleGdImage',   'jpeg' => 'handleGdImage',
    'png'  => 'handleGdImage',   'gif'  => 'handleGdImage',
    'pdf'  => 'handlePdf',       'psd'  => 'handleImageMagick',
    'svg'  => 'handleSvg',       'mp4'  => 'handleVideo',
    'docx' => 'handleOffice',    'xlsx' => 'handleOffice',
];

// Video thumbnail: single frame at 1 second
private function handleVideo(string $source, string $thumbPath, string $previewPath): void
{
    $cmd = "ffmpeg -i {$source} -ss 00:00:01 -frames:v 1 -vf scale=300:-1 {$thumbPath}";
    exec($cmd);
}
```

---

### 4.7 Taxonomy Management

**Page:** `Taxonomy.vue` (~200 lines)  
**Job:** `ApplyTaxonomy.php` (~250 lines)  
**Service:** `TaxonomyService.php`

Controlled vocabulary management with 945+ synonym rules, fuzzy matching, and AI tag validation.

**Sub-features:**
- Tree view of groups → rules (raw term → canonical term)
- Real asset counts and rule counts per group (synced with DB)
- AI accuracy metric (% auto-approved tags)
- Add/edit/delete/toggle rules
- CSV bulk import
- Synonym normalization (4 phases): normalize → validate → group-vote → dedup
- Fuzzy matching at ≥85% similarity for uncontrolled terms

**Example — Taxonomy validation with fuzzy matching (PHP):**
```php
// Phase 2: Controlled vocabulary validation
foreach ($asset->tags as $tag) {
    if (!$taxonomyService->isControlledTerm($tag->tag)) {
        $closest = $taxonomyService->findClosestTerm($tag->tag);
        if ($closest && $closest[2] >= 0.85) {
            $tag->update(['tag' => strtolower($closest[0])]);
        }
    }
}

// Phase 3: Group classification voting
$groupVotes = [];
foreach ($asset->tags as $tag) {
    $rule = TaxonomyRule::where('canonical_term', $tag->tag)->first();
    if ($rule) {
        $groupVotes[$rule->group_hint] = ($groupVotes[$rule->group_hint] ?? 0) + $tag->confidence;
    }
}
```

---

### 4.8 Pipeline Monitor

**Page:** `Pipeline.vue` (~135 lines)  
**Controller:** `PageController::pipeline()`

Kanban-style horizontal board showing real-time distribution of assets across processing stages.

**Sub-features:**
- Horizontally scrolling stage columns with snap points
- Color-coded stages (blue → violet → indigo → amber → emerald)
- Per-asset progress indicators
- Bottom stats bar (total in pipeline, success rate, failed count)
- Click-through to asset preview

**Example — Pipeline kanban rendering (Vue):**
```vue
<div v-for="(stage, key) in stageData" :key="key"
  class="min-w-[260px] snap-center glass rounded-2xl overflow-hidden">
  <div :class="['px-4 py-3', stageStyles[key].headerBg]">
    <span class="text-white font-bold text-xs">{{ stage.label }}</span>
    <span class="bg-white/20 text-white text-[10px] px-2 py-0.5 rounded-full">
      {{ stage.items.length }}
    </span>
  </div>
  <div v-for="item in stage.items" :key="item.id"
    class="px-4 py-2.5 border-b border-slate-50 dark:border-slate-800 cursor-pointer"
    @click="router.visit('/preview/' + item.id)">
    <p class="text-xs font-semibold">{{ item.name }}</p>
  </div>
</div>
```

---

### 4.9 Human-in-the-Loop Review

**Page:** `Review.vue` (~200 lines)  
**Controller:** `PageController::review()` + `ActionController::reviewApprove/Reject/Override/Flag()`

Split-panel review interface for human validation of AI-classified assets.

**Sub-features:**
- Left panel: Filterable queue list (High/Medium/Low priority based on confidence)
- Right panel: Full preview with AI analysis (tags with confidence bars)
- Actions: Approve, Reject, Override group, Flag for escalation
- Add/remove tags inline during review
- Confidence-based priority buckets
- Activity logging via Spatie Activity Log

**Example — Review approve action (PHP):**
```php
public function reviewApprove(Request $request, $id)
{
    $asset = Asset::findOrFail($id);
    $asset->update([
        'review_status' => 'approved',
        'reviewed_by'   => $request->user()->id,
        'reviewed_at'   => now(),
    ]);
    activity()->performedOn($asset)->causedBy($request->user())
        ->log('Asset approved');
    return back();
}
```

---

### 4.10 Analytics Dashboard

**Page:** `Analytics.vue` (~180 lines)  
**Controller:** `PageController::analytics()`

Data analytics with time-range filtering, trend charts, and CSV export.

**Sub-features:**
- Time range toggle (24h / 7d / 30d / 90d)
- 4 KPI cards with trend indicators
- Grouped bar chart (uploads / downloads / reviews over time)
- CSS `conic-gradient` donut chart for asset type distribution
- Top performing assets leaderboard
- CSV export
- Activity feed

**Example — Donut chart with conic-gradient (Vue):**
```vue
const donutGradient = computed(() => {
  const dist = props.groupDistribution || [];
  const total = dist.reduce((s, g) => s + g.count, 0) || 1;
  let angle = 0;
  const stops = dist.map((g, i) => {
    const start = angle;
    angle += (g.count / total) * 360;
    return `${donutColors[i]} ${start}deg ${angle}deg`;
  });
  return `conic-gradient(${stops.join(', ')})`;
});
```

---

### 4.11 Collections Manager

**Page:** `Collections.vue` (~210 lines)  
**Controller:** `PageController::collections()` + `ActionController::collectionStore/Update/Destroy()`

Themed asset groups with role-based access control.

**Sub-features:**
- Create / edit / delete collections
- Access levels: public, private, role-based
- Search and filter by access level
- Featured collection hero banner
- 3-column card grid with gradient thumbnails
- Asset add/remove from collections
- Role-based permission management per collection
- Admin bulk-delete with floating bar

**Example — Collection with role-based access (PHP):**
```php
// Collection model
public function isAccessibleByRole(string $role): bool
{
    if ($this->access_level === 'public') return true;
    return $this->permissions()->where('role', $role)->exists();
}
```

---

### 4.12 Full-Text Search

**Page:** `Search.vue` (~260 lines)  
**Controller:** `ActionController::search()`  
**Engine:** Meilisearch via Laravel Scout

Faceted full-text search with synonym expansion and paginated results.

**Sub-features:**
- Large search bar with auto-focus
- Quick filter tag chips
- Faceted sidebar (extension, status, group — with result counts)
- Sort by relevance, date, name
- Active filter chips with clear-all
- 3-column results grid
- Synonym expansion via TaxonomyService (e.g., "burger" also finds "hamburger")
- Inertia pagination

**Example — Search with synonym expansion (PHP):**
```php
public function expandSearchTerms(string $query): array
{
    $terms = [$query];
    $synonymMap = $this->getSynonymMap();      // raw → canonical
    $reverseMap = $this->getReverseSynonymMap(); // canonical → [raws]

    // Bidirectional: query → canonical → siblings
    if (isset($synonymMap[$lower])) {
        $canonical = $synonymMap[$lower];
        $terms[] = $canonical;
        $terms = array_merge($terms, $reverseMap[$canonical] ?? []);
    }
    return array_unique($terms);
}
```

---

### 4.13 Version Management

**Page:** `Preview.vue` (version history section)  
**Controller:** `ActionController::uploadVersion()` / `restoreVersion()`

File version tracking with rollback capability.

**Sub-features:**
- Upload new version with change notes
- Version history strip with thumbnails
- Restore any previous version as current
- SHA-256 hash per version for integrity
- Automatic version number incrementing

**Example — Version upload (PHP):**
```php
public function uploadVersion(Request $request, $id)
{
    $asset = Asset::findOrFail($id);
    $file = $request->file('file');
    $nextVersion = $asset->versions()->max('version_number') + 1;
    $path = $file->store("versions/{$id}", 'assets');

    AssetVersion::create([
        'asset_id'       => $asset->id,
        'version_number' => $nextVersion,
        'file_path'      => $path,
        'file_size'      => $file->getSize(),
        'sha256_hash'    => hash_file('sha256', $file->getRealPath()),
        'uploaded_by'    => $request->user()->id,
        'change_notes'   => $request->input('change_notes', 'New version'),
    ]);
}
```

---

### 4.14 Google Drive Integration

**Page:** `GoogleDrive.vue` (~240 lines)  
**Controller:** `GoogleDriveController.php` (~300 lines)  
**Job:** `DriveImportJob.php` (~200 lines)

Full OAuth 2.0 Google Drive integration for browsing and importing files.

**Sub-features:**
- OAuth 2.0 consent flow with token storage
- Browse modes: folder navigation, recent files, shared files
- Breadcrumb folder navigation
- Multi-file selection and batch import (up to 50 files)
- Google Docs/Sheets/Slides auto-export as PDF
- File format filtering
- Token auto-refresh
- Disconnect with token revocation

**Example — Google Docs export handling (PHP):**
```php
$exportMime = match ($mimeType) {
    'application/vnd.google-apps.document'     => 'application/pdf',
    'application/vnd.google-apps.spreadsheet'  => 'application/pdf',
    'application/vnd.google-apps.presentation' => 'application/pdf',
    default => null,
};

if ($exportMime) {
    $url = "https://www.googleapis.com/drive/v3/files/{$fileId}/export?mimeType=" . urlencode($exportMime);
} else {
    $url = "https://www.googleapis.com/drive/v3/files/{$fileId}?alt=media";
}
```

---

### 4.15 RBAC — Role-Based Access Control

**Page:** `Permissions.vue` (~330 lines)  
**Controller:** `ActionController::assignRole()` / `createUser()` / `deleteUser()`  
**Library:** Spatie Laravel Permission

4-role RBAC system with group-based asset visibility.

**Roles & Permissions:**

| Role | Accessible Groups |
|---|---|
| **Admin** | All 15 groups (7 visual + 8 document) |
| **Food Team** | FOOD, DOC-CLIENT, DOC-OPS |
| **Media Team** | MEDIA, DOC-CLIENT, DOC-OPS |
| **Marketing Team** | GENBUS, LIFE, DOC-MKT, DOC-WEB |

**Sub-features:**
- Role overview cards with permission chips
- Searchable user table with inline role assignment
- Permission matrix (role × permission grid)
- Create / edit / delete users
- Invite users via email
- RBAC enforced on download, preview, and thumbnail routes

**Example — RBAC configuration (PHP):**
```php
// config/gam.php
'role_groups' => [
    'Admin'          => ['FOOD','MEDIA','GENBUS','GEO','NATURE','LIFE','SPEC',
                         'DOC-CLIENT','DOC-MKT','DOC-WEB','DOC-DATA','DOC-PROD',
                         'DOC-OPS','DOC-LEGAL','DOC-CLD'],
    'Food Team'      => ['FOOD', 'DOC-CLIENT', 'DOC-OPS'],
    'Media Team'     => ['MEDIA', 'DOC-CLIENT', 'DOC-OPS'],
    'Marketing Team' => ['GENBUS', 'LIFE', 'DOC-MKT', 'DOC-WEB'],
],
```

---

### 4.16 Audit Log

**Page:** `AuditLog.vue` (~185 lines)  
**Controller:** `PageController::auditLog()` + `ActionController::auditLogExport()`  
**Library:** Spatie Activity Log

Full audit trail of all system actions with filtering and CSV export.

**Sub-features:**
- 4 KPI cards (total entries, active users, log channels, today's count)
- Multi-field filter bar (action, user, log name, date range)
- Activity stream with contextual icons/colors
- Pagination
- CSV export with preserved filter parameters

**Example — Activity logging on Asset model (PHP):**
```php
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Asset extends Model
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['group_classification', 'review_status', 'description', 'pipeline_status'])
            ->logOnlyDirty()
            ->setDescriptionForEvent(fn (string $eventName) => "Asset {$eventName}");
    }
}
```

---

### 4.17 Documents (BookStack Integration)

**Page:** `Documents.vue` (~195 lines)  
**Controller:** `PageController::documents()` + `ActionController::createDocument()` / `documentContent()`

Knowledge base backed by BookStack CMS for creating SOPs, guides, and policies.

**Sub-features:**
- Create documents inline (title, book, content)
- Search bar with category tabs (All / Guides / SOPs / Policies / References)
- 2-column card grid with tags
- Full-document modal viewer (fetches HTML content via AJAX)
- Content rendered as HTML from BookStack API

---

### 4.18 Notes (Trilium Integration)

**Page:** `Notes.vue` (~145 lines)  
**Controller:** `PageController::notes()` + `ActionController::createNote()` / `likeNote()` / `replyToNote()`

Team collaboration space backed by Trilium Notes.

**Sub-features:**
- Quick compose textarea with toolbar (@mention, #tag, attachment, emoji)
- Notes feed with avatars, timestamps, and linked assets
- Like button with counters
- Inline threaded replies
- Error banner for API connectivity issues

---

### 4.19 Settings & Backup

**Page:** `Settings.vue` (~290 lines)  
**Controller:** `PageController::settings()` + `ActionController::settingsUpdate()` / `runBackup()` / `createToken()`

System administration panel for configuration, API tokens, and database backups.

**Sub-features:**
- Sticky sidebar nav with smooth scroll-to-section
- General settings (site name, toggles)
- Pipeline parameters (AI confidence threshold slider, concurrent jobs)
- Integration cards (S3, Slack, Google Drive status)
- API token CRUD with copy-to-clipboard (Laravel Sanctum)
- Backup management (run backup, download, delete)

---

### 4.20 User Profile

**Page:** `Profile.vue` (~310 lines)  
**Controller:** `PageController::profile()` + `ActionController::profileUpdate()` / `profilePassword()`

User dashboard with stats, activity, and account management.

**Sub-features:**
- Hero banner with avatar initials
- 5-column stats grid (uploads, reviews, approval rate, collections, storage)
- Recent activity timeline
- Managed collections grid
- Permissions summary
- Performance metrics with progress bars
- Team members list
- Active sessions
- Edit profile modal (name, email, password)

---

### 4.21 REST API

**Controller:** `ApiController.php` (~200 lines)  
**Auth:** Laravel Sanctum Bearer tokens

External API for programmatic asset management.

**Endpoints:**

| Method | Path | Purpose |
|---|---|---|
| `POST` | `/api/assets/import` | Upload file (500MB max), dedup, dispatch pipeline |
| `GET` | `/api/assets/status/{id}` | Poll pipeline processing status |
| `GET` | `/api/assets/{id}` | Full asset detail with tags & versions |
| `GET` | `/api/assets` | Paginated list (filterable, role-scoped) |
| `GET` | `/api/user` | Authenticated user info |

**Example — API file import (PHP):**
```php
public function import(Request $request)
{
    $request->validate(['file' => 'required|file|max:512000']);
    $file = $request->file('file');
    $hash = hash_file('sha256', $file->getRealPath());

    if (Asset::where('sha256_hash', $hash)->exists()) {
        return response()->json(['error' => 'Duplicate file'], 409);
    }

    $asset = Asset::create([...]);
    ProcessAssetPipeline::dispatch($asset);

    return response()->json([
        'message'  => 'File accepted for processing',
        'asset_id' => $asset->id,
    ], 202);
}
```

---

## 5. Design System

### 5.1 Visual Language

- **Glassmorphism cards**: Semi-transparent backgrounds with 16px backdrop blur
- **Gradient palette**: Primary indigo-500 → violet-500 gradient for CTAs
- **Dark mode**: Full dark mode via CSS class toggle (localStorage persisted)
- **Icons**: Remix Icons (`ri-*`) via CDN
- **Typography**: System font stack with Tailwind defaults

### 5.2 Animation System

- Scroll-reveal animations via IntersectionObserver
- 4 animation variants: `anim-enter` (up), `anim-enter-left`, `anim-enter-right`, `anim-enter-scale`
- Staggered delays via `data-delay` attributes
- `prefers-reduced-motion` accessibility respect

**Example — Glassmorphism + animations (CSS):**
```css
.glass {
  background: rgba(255, 255, 255, 0.65);
  backdrop-filter: blur(16px) saturate(180%);
  border: 1.5px solid rgba(99, 102, 241, 0.22);
}

.anim-enter {
  opacity: 0;
  transform: translateY(24px);
  transition: opacity 0.6s cubic-bezier(0.16, 1, 0.3, 1),
              transform 0.6s cubic-bezier(0.16, 1, 0.3, 1);
}
.anim-enter.visible {
  opacity: 1;
  transform: translateY(0);
}

@media (prefers-reduced-motion: reduce) {
  .anim-enter, .anim-enter-left, .anim-enter-right, .anim-enter-scale {
    opacity: 1;
    transform: none;
    transition: none;
  }
}
```

---

## 6. Database Schema

### 6.1 Core Tables

| Table | Records | Purpose |
|---|---|---|
| `assets` | 18+ | Master asset records with metadata, pipeline/review status |
| `asset_tags` | ~200+ | AI-generated and manual tags with confidence scores |
| `asset_versions` | ~20+ | File version history with SHA-256 hashes |
| `staging_assets` | transient | Upload staging before pipeline processing |
| `taxonomy_rules` | 603 | Synonym mappings (raw → canonical + group hint) |
| `taxonomy_terms` | ~700+ | Controlled vocabulary for all 15 groups |
| `collections` | variable | Named asset groups |
| `collection_asset` | pivot | Many-to-many asset ↔ collection |
| `collection_permissions` | pivot | Role-based collection access |
| `users` | variable | User accounts with Spatie roles |
| `google_drive_tokens` | per-user | OAuth token storage |
| `activity_log` | growing | Full audit trail (Spatie) |
| `jobs` / `failed_jobs` | transient | Redis queue persistence |

### 6.2 Key Asset Table Columns

```
id, original_filename, original_path, file_extension, file_size,
mime_type, sha256_hash (indexed), upload_source, uploader_ip,
ingested_at, group_classification, group_confidence,
description, pipeline_status, preview_status, review_status,
review_reason, is_master, derived_from, storage_disk,
storage_path, preview_path, thumbnail_path,
uploaded_by, reviewed_by, reviewed_at,
created_at, updated_at, deleted_at (soft delete)
```

---

## 7. Docker Deployment

### 7.1 Services (10 Containers)

| Container | Image | Port | Purpose |
|---|---|---|---|
| `gam-app` | Custom (PHP 8.4 + Nginx) | 8000 | Main application |
| `gam-horizon` | Same as app | — | Queue worker |
| `gam-mysql` | mysql:8.0 | 3306 | Primary database |
| `gam-redis` | redis:7-alpine | 6379 | Queue + cache + sessions |
| `gam-meilisearch` | meilisearch:v1.12 | 7700 | Full-text search |
| `gam-bookstack` | linuxserver/bookstack | 6875 | Document CMS |
| `gam-trilium` | zadam/trilium | 8081 | Knowledge notes |
| `gam-ckan` | Custom | 5000 | Dataset catalogue |
| `gam-ckan-db` | postgres:15 | — | CKAN sidecar database |
| `gam-ckan-solr` | Custom Solr | — | CKAN search backend |
| `gam-soketi` | soketi:1.6 | 6001 | WebSocket server |

### 7.2 Multi-Stage Dockerfile

```dockerfile
# Stage 1: Composer dependencies
FROM composer:2 AS composer-deps
RUN composer install --no-dev --prefer-dist

# Stage 2: Node/Vite build
FROM node:20-alpine AS node-build
RUN npm ci && npm run build

# Stage 3: Production app image
FROM php:8.4-fpm AS app
RUN apt-get install -y ghostscript imagemagick inkscape \
    poppler-utils ffmpeg libreoffice-core libreoffice-writer libreoffice-calc
COPY --from=composer-deps /app/vendor ./vendor
COPY --from=node-build /app/public/build ./public/build
EXPOSE 80
CMD ["/usr/bin/supervisord"]
```

---

## 8. Security Features

| Feature | Implementation |
|---|---|
| **Authentication** | Laravel session auth + Sanctum API tokens |
| **Rate Limiting** | 5 attempts/minute per IP+email on login |
| **RBAC** | 4 roles with group-based asset visibility |
| **File Integrity** | SHA-256 hash on every upload and version |
| **Deduplication** | Hash-based duplicate detection (409 on conflict) |
| **CSRF Protection** | Laravel middleware on all POST/PUT/DELETE |
| **Soft Deletes** | Assets and collections recoverable |
| **Audit Trail** | Spatie Activity Log on all model changes |
| **Input Validation** | Laravel Form Request validation throughout |
| **Password Security** | bcrypt hashing |

---

## 9. Classification Taxonomy

### 9.1 Visual Asset Groups (7)

| Code | Name | Description |
|---|---|---|
| `FOOD` | Food & Agriculture | Farm-to-fork, ingredients, restaurants, beverages |
| `MEDIA` | Media Industry | Broadcast, streaming, digital publishing, production |
| `GENBUS` | General Business | Meetings, office, factories, retail |
| `GEO` | Location/Geography | US regions, cities, skylines, landmarks |
| `NATURE` | Nature & Environment | Landscapes, water, weather, ecosystems |
| `LIFE` | Lifestyle | Family, leisure, sports, travel, wellness |
| `SPEC` | Specialty | Concepts, icons, diagrams, infographics, abstract |

### 9.2 Document Groups (8)

| Code | Name | Description |
|---|---|---|
| `DOC-CLIENT` | Client Deliverables | Final client-facing outputs |
| `DOC-MKT` | Marketing & Sales | Decks, proposals, messaging |
| `DOC-WEB` | Web / Content Ops | Wireframes, IA, content plans |
| `DOC-DATA` | Data & Analysis | Analyses, charts, methodology |
| `DOC-PROD` | Product / BPM | PRDs, alignment docs, workflows |
| `DOC-OPS` | Operations | SOPs, onboarding, checklists |
| `DOC-LEGAL` | Legal / Contracts | MSAs, SOWs, NDAs (restricted) |
| `DOC-CLD` | CLDs | Logical layer docs (highly restricted) |

---

## 10. Summary

GAM is a production-grade digital asset management platform that combines:

- **AI-powered classification** using GPT-4o-mini vision with 945+ taxonomy rules
- **6-stage processing pipeline** with graceful fallbacks at every stage
- **Multi-format preview generation** supporting 20+ file types via 7 CLI tools
- **Role-based access control** with 4 roles and 15 classification groups
- **Full audit trail** logging every model change
- **External integrations** with Google Drive, BookStack, Trilium, and CKAN
- **Modern SPA frontend** using Vue 3 + Inertia.js with glassmorphism UI
- **Containerized deployment** via Docker Compose with 10+ services
- **REST API** for programmatic access with Sanctum token authentication

The system is designed to scale from small teams to enterprise workloads, with SHA-256 deduplication, Redis-backed queue processing via Laravel Horizon, and Meilisearch for sub-millisecond full-text search.
