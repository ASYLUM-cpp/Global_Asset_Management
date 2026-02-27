<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\AssetTag;
use App\Models\Collection;
use App\Models\TaxonomyRule;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;

class PageController extends Controller
{
    public function dashboard(Request $request): Response
    {
        $user = $request->user();
        $assetsQuery = Asset::forUser($user);
        $totalAssets = (clone $assetsQuery)->count();

        return Inertia::render('Dashboard', [
            'totalAssets'       => $totalAssets,
            'aiClassified'      => (clone $assetsQuery)->whereNotNull('group_classification')->count(),
            'aiClassifiedPct'   => $totalAssets > 0 ? round((clone $assetsQuery)->whereNotNull('group_classification')->count() / $totalAssets * 100, 1) : 0,
            'previewDone'       => (clone $assetsQuery)->where('preview_status', 'done')->count(),
            'previewPct'        => $totalAssets > 0 ? round((clone $assetsQuery)->where('preview_status', 'done')->count() / $totalAssets * 100, 1) : 0,
            'storageUsedBytes'  => Asset::sum('file_size'),
            'storageCapacity'   => config('gam.storage_capacity', 60 * 1024 * 1024 * 1024),
            'pendingReview'     => Asset::where('review_status', 'pending')->count(),
            'autoApproved'      => Asset::where('review_status', 'approved')->whereNull('reviewed_by')->count(),
            'escalations'       => Asset::where('review_status', 'rejected')->count(),
            'reviewCapacityPct' => Asset::where('review_status', 'pending')->count() > 0
                ? round(Asset::where('review_status', 'pending')->count() / max(Asset::count(), 1) * 100, 0)
                : 0,
            'reviewQueue'      => Asset::where('review_status', 'pending')
                ->with('tags')
                ->latest()
                ->take(3)
                ->get()
                ->map(fn ($a) => [
                    'id'         => $a->id,
                    'name'       => $a->original_filename,
                    'extension'  => $a->file_extension,
                    'group'      => $a->group_classification,
                    'confidence' => $a->tags->avg('confidence'),
                    'topTags'    => $a->tags->sortByDesc('confidence')->take(3)->pluck('tag')->toArray(),
                ]),
            'pipelineStages'   => [
                ['stage' => 'Ingestion',     'count' => Asset::where('pipeline_status', 'queued')->count()],
                ['stage' => 'Hashing',       'count' => Asset::where('pipeline_status', 'hashing')->count()],
                ['stage' => 'Preview',       'count' => Asset::where('pipeline_status', 'previewing')->count()],
                ['stage' => 'AI Tagging',    'count' => Asset::where('pipeline_status', 'tagging')->count()],
                ['stage' => 'Classification','count' => Asset::where('pipeline_status', 'classifying')->count()],
                ['stage' => 'Indexing',      'count' => Asset::where('pipeline_status', 'indexing')->count()],
                ['stage' => 'Complete',      'count' => Asset::where('pipeline_status', 'done')->count()],
            ],
            'recentActivity'   => Activity::with('causer')
                ->latest()
                ->take(5)
                ->get()
                ->map(fn ($a) => [
                    'description' => $a->description,
                    'causer'      => $a->causer?->name ?? 'System',
                    'time'        => $a->created_at->diffForHumans(),
                    'log_name'    => $a->log_name,
                ]),
            'taxonomyBreakdown' => Asset::whereNotNull('group_classification')
                ->select('group_classification', DB::raw('count(*) as count'))
                ->groupBy('group_classification')
                ->get()
                ->map(fn ($g) => [
                    'group' => $g->group_classification,
                    'count' => $g->count,
                    'pct'   => $totalAssets > 0 ? round($g->count / $totalAssets * 100, 1) : 0,
                ]),
            'serviceHealth' => $this->checkServiceHealth(),
            'uploadTrend' => collect(range(6, 0))->map(function ($daysAgo) {
                $date = now()->subDays($daysAgo);
                return [
                    'date'  => $date->format('D'),
                    'count' => Asset::whereDate('ingested_at', $date->toDateString())->count(),
                ];
            }),
        ]);
    }

    public function assets(Request $request): Response
    {
        $user = $request->user();
        $baseQuery = Asset::forUser($user)->where('pipeline_status', 'done');
        $query = clone $baseQuery;

        // Filters
        if ($request->filled('group')) {
            $query->where('group_classification', $request->group);
        }
        if ($request->filled('extension')) {
            $query->where('file_extension', $request->extension);
        }
        if ($request->filled('status')) {
            $query->where('review_status', $request->status);
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $taxonomyService = app(\App\Services\TaxonomyService::class);
            $expandedTerms = $taxonomyService->expandSearchTerms($search);
            $query->where(function ($q) use ($search, $expandedTerms) {
                $q->where('original_filename', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
                // Also search tags with synonym-expanded terms
                foreach ($expandedTerms as $term) {
                    $q->orWhereHas('tags', fn ($tq) => $tq->where('tag', 'like', "%{$term}%"));
                }
            });
        }
        if ($request->filled('tag')) {
            $tag = $request->tag;
            // Expand tag filter with synonyms for normalized matching
            $taxonomyService = $taxonomyService ?? app(\App\Services\TaxonomyService::class);
            $expandedTags = $taxonomyService->expandSearchTerms($tag);
            $query->whereHas('tags', fn ($q) => $q->where(function ($qq) use ($expandedTags) {
                foreach ($expandedTags as $i => $t) {
                    $i === 0 ? $qq->where('tag', 'like', "%{$t}%") : $qq->orWhere('tag', 'like', "%{$t}%");
                }
            }));
        }
        if ($request->filled('date_from')) {
            $query->whereDate('ingested_at', '>=', $request->input('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->whereDate('ingested_at', '<=', $request->input('date_to'));
        }

        // Sorting
        $sortMap = [
            'newest'  => ['ingested_at', 'desc'],
            'oldest'  => ['ingested_at', 'asc'],
            'name'    => ['original_filename', 'asc'],
            'size'    => ['file_size', 'desc'],
        ];
        $sort = $sortMap[$request->input('sort', 'newest')] ?? $sortMap['newest'];
        $query->orderBy($sort[0], $sort[1]);

        $totalCount = (clone $baseQuery)->count();

        // Compute filter counts for sidebar
        $extensionCounts = (clone $baseQuery)
            ->select('file_extension', DB::raw('count(*) as count'))
            ->groupBy('file_extension')
            ->pluck('count', 'file_extension');

        $statusCounts = (clone $baseQuery)
            ->select('review_status', DB::raw('count(*) as count'))
            ->groupBy('review_status')
            ->pluck('count', 'review_status');

        // Real tags from DB
        $popularTags = \App\Models\AssetTag::select('tag', DB::raw('count(*) as count'))
            ->groupBy('tag')
            ->orderByDesc('count')
            ->take(15)
            ->pluck('count', 'tag');

        $assets = $query->with('tags', 'uploader')
            ->paginate(24)
            ->through(fn ($a) => [
                'id'          => $a->id,
                'name'        => $a->original_filename,
                'extension'   => $a->file_extension,
                'size'        => $a->file_size_formatted,
                'sizeBytes'   => $a->file_size,
                'mime'        => $a->mime_type,
                'group'       => $a->group_classification,
                'groupColor'  => $a->group_badge_color,
                'status'      => $a->review_status,
                'pipeline'    => $a->pipeline_status,
                'description' => $a->description,
                'tags'        => $a->tags->sortByDesc('confidence')->take(3)->pluck('tag')->toArray(),
                'uploader'    => $a->uploader?->name,
                'uploadDate'  => $a->ingested_at?->format('M d, Y'),
                'previewStatus' => $a->preview_status,
                'thumbnailUrl'  => $a->thumbnail_path ? '/serve/thumbnail/' . $a->id : null,
            ]);

        return Inertia::render('Assets', [
            'assets'          => $assets,
            'totalCount'      => $totalCount,
            'filters'         => $request->only('group', 'extension', 'status', 'search', 'sort', 'tag', 'date_from', 'date_to'),
            'groups'          => array_keys(config('gam.groups')),
            'extensions'      => Asset::forUser($user)->distinct()->pluck('file_extension')->filter()->sort()->values(),
            'extensionCounts' => $extensionCounts,
            'statusCounts'    => $statusCounts,
            'popularTags'     => $popularTags,
            'isAdmin'         => $request->user()->hasRole('Admin'),
        ]);
    }

    public function upload(Request $request): Response
    {
        return Inertia::render('Upload', [
            'recentUploads' => Asset::where('uploaded_by', $request->user()->id)
                ->whereNotIn('pipeline_status', ['cancelled'])
                ->latest()
                ->take(5)
                ->get()
                ->map(fn ($a) => [
                    'id'       => $a->id,
                    'name'     => $a->original_filename,
                    'size'     => $a->file_size_formatted,
                    'status'   => $a->pipeline_status,
                    'group'    => $a->group_classification,
                    'uploaded' => $a->created_at->diffForHumans(),
                ]),
            'collections' => Collection::orderBy('name')->pluck('name', 'id'),
            'groups'      => array_keys(config('gam.groups')),
        ]);
    }

    public function pipeline(): Response
    {
        $stages = [
            'queued'      => ['label' => 'Ingestion',      'icon' => 'ri-upload-cloud-line'],
            'hashing'     => ['label' => 'Hashing',        'icon' => 'ri-fingerprint-line'],
            'previewing'  => ['label' => 'Preview Gen',    'icon' => 'ri-image-line'],
            'tagging'     => ['label' => 'AI Tagging',     'icon' => 'ri-robot-line'],
            'classifying' => ['label' => 'Classification', 'icon' => 'ri-folder-chart-line'],
            'indexing'    => ['label' => 'Indexing',        'icon' => 'ri-search-eye-line'],
            'done'        => ['label' => 'Published',      'icon' => 'ri-check-double-line'],
        ];

        $stageData = [];
        foreach ($stages as $status => $meta) {
            $assets = Asset::where('pipeline_status', $status)
                ->latest()
                ->take(5)
                ->get()
                ->map(fn ($a) => [
                    'id'        => $a->id,
                    'name'      => $a->original_filename,
                    'extension' => $a->file_extension,
                    'size'      => $a->file_size_formatted,
                ]);

            $stageData[] = [
                'key'    => $status,
                'label'  => $meta['label'],
                'icon'   => $meta['icon'],
                'count'  => Asset::where('pipeline_status', $status)->count(),
                'assets' => $assets,
            ];
        }

        return Inertia::render('Pipeline', [
            'stages'       => $stageData,
            'totalInPipeline' => Asset::whereNotIn('pipeline_status', ['done', 'failed'])->count(),
            'successRate'  => Asset::count() > 0
                ? round(Asset::where('pipeline_status', 'done')->count() / Asset::count() * 100, 1)
                : 0,
            'failedCount'  => Asset::where('pipeline_status', 'failed')->count(),
        ]);
    }

    public function review(Request $request): Response
    {
        $pending = Asset::where('review_status', 'pending')
            ->with(['tags' => fn ($q) => $q->orderByDesc('confidence'), 'uploader'])
            ->latest()
            ->get()
            ->map(fn ($a) => [
                'id'          => $a->id,
                'name'        => $a->original_filename,
                'extension'   => $a->file_extension,
                'size'        => $a->file_size_formatted,
                'mime'        => $a->mime_type,
                'group'       => $a->group_classification,
                'groupColor'  => $a->group_badge_color,
                'description' => $a->description,
                'confidence'  => $a->group_confidence,
                'reason'      => $a->review_reason ?? 'Low AI confidence score',
                'tags'        => $a->tags->map(fn ($t) => [
                    'tag'        => $t->tag,
                    'confidence' => round($t->confidence * 100),
                    'color'      => $t->confidence_color,
                    'approved'   => $t->auto_approved,
                ])->toArray(),
                'uploader'    => $a->uploader?->name,
                'uploadDate'  => $a->ingested_at?->format('M d, Y'),
            ]);

        return Inertia::render('Review', [
            'pendingAssets' => $pending,
            'pendingCount'  => $pending->count(),
            'groups'        => array_keys(config('gam.groups')),
        ]);
    }

    public function analytics(Request $request): Response
    {
        $user = $request->user();

        // Upload trend — last 14 days
        $uploadTrend = Asset::where('ingested_at', '>=', now()->subDays(14))
            ->select(DB::raw("DATE(ingested_at) as date"), DB::raw('count(*) as count'))
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(fn ($d) => ['date' => $d->date, 'count' => $d->count]);

        // Group distribution
        $groupDist = Asset::whereNotNull('group_classification')
            ->select('group_classification', DB::raw('count(*) as count'))
            ->groupBy('group_classification')
            ->get()
            ->map(fn ($g) => ['group' => $g->group_classification, 'count' => $g->count]);

        // Top assets by download proxy (file_size as proxy until download tracking)
        $topAssets = Asset::where('review_status', 'approved')
            ->orderByDesc('file_size')
            ->take(5)
            ->get()
            ->map(fn ($a) => [
                'name'      => $a->original_filename,
                'group'     => $a->group_classification,
                'downloads' => $a->file_size > 0 ? intval($a->file_size / 1024) : 0,
                'size'      => $a->file_size_formatted,
            ]);

        // Compute real review time where possible (MySQL-compatible)
        $avgReviewSeconds = Asset::whereNotNull('reviewed_at')
            ->whereNotNull('ingested_at')
            ->selectRaw('AVG(TIMESTAMPDIFF(SECOND, ingested_at, reviewed_at)) as avg_seconds')
            ->value('avg_seconds');
        $avgReviewTime = $avgReviewSeconds
            ? ($avgReviewSeconds > 3600 ? round($avgReviewSeconds / 3600, 1) . 'h' : round($avgReviewSeconds / 60, 1) . 'm')
            : '—';

        // Download count from activity log as a proxy
        $totalDownloads = Activity::where('description', 'like', '%download%')->count();

        return Inertia::render('Analytics', [
            'totalUploads'      => Asset::count(),
            'totalDownloads'    => $totalDownloads,
            'avgReviewTime'     => $avgReviewTime,
            'rejectionRate'     => Asset::count() > 0
                ? round(Asset::where('review_status', 'rejected')->count() / Asset::count() * 100, 1) : 0,
            'uploadTrend'     => $uploadTrend,
            'groupDistribution' => $groupDist,
            'topAssets'       => $topAssets,
            'recentActivity'  => Activity::with('causer')
                ->latest()
                ->take(5)
                ->get()
                ->map(fn ($a) => [
                    'description' => $a->description,
                    'causer'      => $a->causer?->name ?? 'System',
                    'time'        => $a->created_at->diffForHumans(),
                ]),
        ]);
    }

    public function collections(Request $request): Response
    {
        $user = $request->user();
        $collections = Collection::withCount('assets')
            ->with('creator')
            ->latest()
            ->get()
            ->filter(function ($col) use ($user) {
                if ($user->hasRole('Admin')) return true;
                return $col->access_level === 'public' || $col->isAccessibleByRole($user->primary_role);
            })
            ->map(fn ($c) => [
                'id'          => $c->id,
                'name'        => $c->name,
                'description' => $c->description,
                'access'      => $c->access_level,
                'assetCount'  => $c->assets_count,
                'creator'     => $c->creator?->name,
                'created'     => $c->created_at->format('M d, Y'),
            ])
            ->values();

        return Inertia::render('Collections', [
            'collections' => $collections,
            'featured'    => $collections->first() ? [
                'name'        => $collections->first()['name'],
                'description' => $collections->first()['description'] ?? 'Featured collection',
                'assetCount'  => $collections->first()['assetCount'],
                'updated'     => $collections->first()['created'],
            ] : null,
            'isAdmin' => $request->user()->hasRole('Admin'),
        ]);
    }

    public function taxonomy(): Response
    {
        $rules = TaxonomyRule::orderBy('group_hint')
            ->orderBy('raw_term')
            ->get()
            ->map(fn ($r) => [
                'id'        => $r->id,
                'rawTerm'   => $r->raw_term,
                'canonical' => $r->canonical_term,
                'group'     => $r->group_hint,
                'active'    => $r->is_active,
            ]);

        // Count taxonomy rules per group_hint
        $groupRuleCounts = TaxonomyRule::select('group_hint', DB::raw('count(*) as count'))
            ->groupBy('group_hint')
            ->get()
            ->pluck('count', 'group_hint');

        // Count actual assets per group_classification (the real asset counts per group)
        $groupAssetCounts = Asset::whereNotNull('group_classification')
            ->select('group_classification', DB::raw('count(*) as count'))
            ->groupBy('group_classification')
            ->pluck('count', 'group_classification');

        $totalAssets = Asset::count();

        // Per-rule asset counts (how many assets match each taxonomy rule's canonical tag)
        $ruleAssetCounts = DB::table('asset_tags')
            ->join('taxonomy_rules', 'asset_tags.tag', '=', 'taxonomy_rules.canonical_term')
            ->select('taxonomy_rules.id', DB::raw('count(DISTINCT asset_tags.asset_id) as count'))
            ->groupBy('taxonomy_rules.id')
            ->pluck('count', 'id');

        // AI accuracy: % of auto-approved tags
        $totalTags = \App\Models\AssetTag::count();
        $approvedTags = \App\Models\AssetTag::where('auto_approved', true)->count();
        $aiAccuracy = $totalTags > 0 ? round($approvedTags / $totalTags * 100, 1) : 0;

        // Unclassified assets
        $unclassified = Asset::whereNull('group_classification')->count();

        return Inertia::render('Taxonomy', [
            'rules'            => $rules,
            'totalRules'       => $rules->count(),
            'groupRuleCounts'  => $groupRuleCounts,
            'groupAssetCounts' => $groupAssetCounts,
            'totalAssets'      => $totalAssets,
            'groups'           => array_keys(config('gam.groups')),
            'ruleAssetCounts'  => $ruleAssetCounts,
            'aiAccuracy'       => $aiAccuracy,
            'unclassified'     => $unclassified,
        ]);
    }

    public function settings(): Response
    {
        $user = auth()->user();

        // API tokens for current user
        $tokens = $user->tokens->map(fn ($t) => [
            'id'           => $t->id,
            'name'         => $t->name,
            'abilities'    => $t->abilities,
            'last_used_at' => $t->last_used_at?->diffForHumans(),
            'created_at'   => $t->created_at->format('M d, Y'),
        ]);

        // Backup history
        $disk = \Illuminate\Support\Facades\Storage::disk('local');
        $backups = collect($disk->files('backups'))
            ->filter(fn ($f) => str_ends_with($f, '.zip') || str_ends_with($f, '.sql') || str_ends_with($f, '.gz'))
            ->sortByDesc(fn ($f) => $disk->lastModified($f))
            ->values()
            ->take(10)
            ->map(fn ($f) => [
                'filename'  => basename($f),
                'size'      => $this->formatBytesHelper($disk->size($f)),
                'date'      => \Carbon\Carbon::createFromTimestamp($disk->lastModified($f))->format('M d, Y H:i'),
                'dateHuman' => \Carbon\Carbon::createFromTimestamp($disk->lastModified($f))->diffForHumans(),
                'type'      => str_contains(basename($f), 'db') ? 'Database' : (str_contains(basename($f), 'full') ? 'Full' : 'Files'),
            ]);

        // Upload trend (last 7 days)
        $uploadTrend = collect(range(6, 0))->map(function ($daysAgo) {
            $date = now()->subDays($daysAgo);
            return [
                'date'  => $date->format('M d'),
                'count' => Asset::whereDate('ingested_at', $date->toDateString())->count(),
            ];
        });

        return Inertia::render('Settings', [
            'aiThreshold'     => config('gam.ai.confidence_threshold'),
            'aiModel'         => config('gam.ai.model'),
            'groups'          => config('gam.groups'),
            'storageUsed'     => Asset::sum('file_size'),
            'storageCapacity' => config('gam.storage_capacity', 60 * 1024 * 1024 * 1024),
            'totalAssets'     => Asset::count(),
            'queuePending'    => Asset::whereNotIn('pipeline_status', ['done', 'failed'])->count(),
            'tokens'          => $tokens,
            'backups'         => $backups,
            'uploadTrend'     => $uploadTrend,
        ]);
    }

    public function permissions(Request $request): Response
    {
        $users = User::with('roles', 'permissions')
            ->get()
            ->map(fn ($u) => [
                'id'          => $u->id,
                'name'        => $u->name,
                'email'       => $u->email,
                'initials'    => $u->initials,
                'roles'       => $u->getRoleNames(),
                'permissions' => $u->getAllPermissions()->pluck('name'),
                'assetCount'  => $u->assets()->count(),
                'joined'      => $u->created_at->format('M d, Y'),
                'lastActive'  => $u->last_login_at ? \Carbon\Carbon::parse($u->last_login_at)->diffForHumans() : $u->created_at->diffForHumans(),
                'lastLoginAt' => $u->last_login_at ? \Carbon\Carbon::parse($u->last_login_at)->toIso8601String() : null,
            ]);

        return Inertia::render('Permissions', [
            'users' => $users,
            'allRoles' => \Spatie\Permission\Models\Role::all()->pluck('name'),
            'allPermissions' => \Spatie\Permission\Models\Permission::all()->pluck('name'),
        ]);
    }

    public function documents(): Response
    {
        $documents = [];
        $bookstackHealth = ['status' => 'unknown', 'latencyMs' => null];

        try {
            $bs = config('services.bookstack');
            $authHeader = 'Token ' . $bs['token_id'] . ':' . $bs['token_secret'];

            // Health check
            $healthStart = microtime(true);
            $healthResp = Http::timeout(5)
                ->withHeaders(['Authorization' => $authHeader])
                ->get($bs['url'] . '/api/books', ['count' => 1]);
            $latency = round((microtime(true) - $healthStart) * 1000);
            if ($healthResp->successful()) {
                $bookstackHealth = ['status' => $latency > 2000 ? 'degraded' : 'ok', 'latencyMs' => $latency];
            } else {
                $bookstackHealth = ['status' => 'down', 'latencyMs' => $latency];
            }

            // Fetch pages from BookStack
            $response = Http::timeout(5)
                ->withHeaders(['Authorization' => $authHeader])
                ->get($bs['url'] . '/api/pages', ['count' => 50]);

            if ($response->successful()) {
                $pages = $response->json('data', []);

                // Fetch books for category mapping
                $booksResp = Http::timeout(5)
                    ->withHeaders(['Authorization' => $authHeader])
                    ->get($bs['url'] . '/api/books', ['count' => 50]);
                $books = collect($booksResp->json('data', []))->keyBy('id');

                $iconMap = [
                    'brand-guidelines' => ['ri-palette-line', 'bg-gradient-to-br from-indigo-400 to-violet-500'],
                    'sop-manual'       => ['ri-flow-chart', 'bg-gradient-to-br from-amber-400 to-orange-500'],
                    'policies-compliance' => ['ri-shield-check-line', 'bg-gradient-to-br from-emerald-400 to-teal-500'],
                ];
                $categoryMap = [
                    'brand-guidelines'    => 'guides',
                    'sop-manual'          => 'sops',
                    'policies-compliance' => 'policies',
                ];

                foreach ($pages as $page) {
                    $book = $books[$page['book_id']] ?? null;
                    $bookSlug = $page['book_slug'] ?? ($book['slug'] ?? 'default');
                    $iconInfo = $iconMap[$bookSlug] ?? ['ri-file-text-line', 'bg-gradient-to-br from-slate-400 to-gray-500'];
                    $category = $categoryMap[$bookSlug] ?? 'references';
                    $updated = \Carbon\Carbon::parse($page['updated_at']);

                    $documents[] = [
                        'id'          => $page['id'],
                        'title'       => $page['name'],
                        'desc'        => strip_tags(mb_substr($page['html'] ?? $page['markdown'] ?? 'No content', 0, 120)) . '...',
                        'author'      => 'Admin',
                        'updated'     => $updated->diffForHumans(),
                        'icon'        => $iconInfo[0],
                        'iconBg'      => $iconInfo[1],
                        'status'       => $page['draft'] ? 'Draft' : 'Published',
                        'statusClass'  => $page['draft']
                            ? 'bg-amber-500/80 text-white'
                            : 'bg-emerald-500/80 text-white',
                        'tags'        => [$category, $book['name'] ?? 'General'],
                        'category'    => $category,
                        'url'         => config('services.bookstack.url') !== 'http://bookstack:80'
                            ? config('services.bookstack.url') . '/books/' . $bookSlug . '/page/' . $page['slug']
                            : 'http://localhost:' . env('BOOKSTACK_PORT', '6875') . '/books/' . $bookSlug . '/page/' . $page['slug'],
                    ];
                }
            }
        } catch (\Throwable $e) {
            \Log::warning('BookStack API error: ' . $e->getMessage());
            $bookstackHealth = ['status' => 'down', 'latencyMs' => null];
        }

        // Pass available books for the inline creation form
        $booksList = [];
        try {
            $bs = config('services.bookstack');
            $authHeader = 'Token ' . $bs['token_id'] . ':' . $bs['token_secret'];
            $booksResp2 = Http::timeout(5)
                ->withHeaders(['Authorization' => $authHeader])
                ->get($bs['url'] . '/api/books', ['count' => 50]);
            if ($booksResp2->successful()) {
                foreach ($booksResp2->json('data', []) as $b) {
                    $booksList[] = ['id' => $b['id'], 'name' => $b['name']];
                }
            }
        } catch (\Throwable $e) {
            // already have books from above, skip silently
        }

        // Fetch BookStack hierarchy (shelves → books → chapters → pages) with caching
        $hierarchy = [];
        try {
            $hierarchy = Cache::remember('gam.bookstack.hierarchy', 300, function () {
                $bs = config('services.bookstack');
                $authHeader = 'Token ' . $bs['token_id'] . ':' . $bs['token_secret'];
                $tree = [];

                // Fetch shelves
                $shelvesResp = Http::timeout(5)
                    ->withHeaders(['Authorization' => $authHeader])
                    ->get($bs['url'] . '/api/shelves', ['count' => 50]);

                if ($shelvesResp->successful()) {
                    foreach ($shelvesResp->json('data', []) as $shelf) {
                        $shelfNode = [
                            'id' => $shelf['id'],
                            'name' => $shelf['name'],
                            'type' => 'shelf',
                            'children' => [],
                        ];

                        // Fetch shelf detail for its books
                        $shelfDetail = Http::timeout(5)
                            ->withHeaders(['Authorization' => $authHeader])
                            ->get($bs['url'] . '/api/shelves/' . $shelf['id']);

                        if ($shelfDetail->successful()) {
                            $shelfBooks = $shelfDetail->json('books', []);
                            foreach ($shelfBooks as $book) {
                                $shelfNode['children'][] = $this->fetchBookTree($book['id'], $authHeader, $bs['url']);
                            }
                        }

                        $tree[] = $shelfNode;
                    }
                }

                // Also fetch unshelved books
                $booksResp = Http::timeout(5)
                    ->withHeaders(['Authorization' => $authHeader])
                    ->get($bs['url'] . '/api/books', ['count' => 50]);

                if ($booksResp->successful()) {
                    $shelvedBookIds = collect($tree)->flatMap(fn ($s) => collect($s['children'])->pluck('id'))->toArray();
                    foreach ($booksResp->json('data', []) as $book) {
                        if (!in_array($book['id'], $shelvedBookIds)) {
                            $tree[] = $this->fetchBookTree($book['id'], $authHeader, $bs['url']);
                        }
                    }
                }

                return $tree;
            });
        } catch (\Throwable $e) {
            \Log::warning('BookStack hierarchy fetch error: ' . $e->getMessage());
        }

        // Extract flat chapters list from hierarchy for the creation form
        $chaptersList = [];
        foreach ($hierarchy as $node) {
            if (($node['type'] ?? '') === 'book') {
                foreach ($node['children'] ?? [] as $child) {
                    if (($child['type'] ?? '') === 'chapter') {
                        $chaptersList[] = ['id' => $child['id'], 'name' => $child['name'], 'book_id' => $node['id']];
                    }
                }
            } elseif (($node['type'] ?? '') === 'shelf') {
                foreach ($node['children'] ?? [] as $book) {
                    foreach ($book['children'] ?? [] as $child) {
                        if (($child['type'] ?? '') === 'chapter') {
                            $chaptersList[] = ['id' => $child['id'], 'name' => $child['name'], 'book_id' => $book['id']];
                        }
                    }
                }
            }
        }

        return Inertia::render('Documents', [
            'documents'       => $documents,
            'books'           => $booksList,
            'chapters'        => $chaptersList,
            'hierarchy'       => $hierarchy,
            'bookstackHealth' => $bookstackHealth,
        ]);
    }

    /**
     * Fetch a book's tree structure (chapters + pages) from BookStack API.
     */
    private function fetchBookTree(int $bookId, string $authHeader, string $baseUrl): array
    {
        $bookDetail = Http::timeout(5)
            ->withHeaders(['Authorization' => $authHeader])
            ->get($baseUrl . '/api/books/' . $bookId);

        $bookData = $bookDetail->json();
        $bookNode = [
            'id' => $bookId,
            'name' => $bookData['name'] ?? 'Book #' . $bookId,
            'type' => 'book',
            'children' => [],
        ];

        // Contents include chapters and direct pages
        $contents = $bookData['contents'] ?? [];
        foreach ($contents as $item) {
            if (($item['type'] ?? '') === 'chapter') {
                $chapterNode = [
                    'id' => $item['id'],
                    'name' => $item['name'],
                    'type' => 'chapter',
                    'children' => [],
                ];
                // Fetch chapter pages
                $chapterPages = $item['pages'] ?? [];
                foreach ($chapterPages as $p) {
                    $chapterNode['children'][] = [
                        'id' => $p['id'],
                        'name' => $p['name'],
                        'type' => 'page',
                    ];
                }
                $bookNode['children'][] = $chapterNode;
            } else {
                // Direct page
                $bookNode['children'][] = [
                    'id' => $item['id'],
                    'name' => $item['name'],
                    'type' => 'page',
                ];
            }
        }

        return $bookNode;
    }

    public function notes(): Response
    {
        $notes = [];
        $triliumHealth = ['status' => 'unknown', 'latencyMs' => null];
        $availableTags = [];

        // ── Tag keyword map: tag name → keywords to match in note titles ──
        // Order matters: more specific keywords first to avoid false matches
        $tagKeywords = [
            'Onboarding'     => ['onboarding'],
            'Taxonomy'       => ['taxonomy'],
            'Classification' => ['classification'],
            'Meeting'        => ['meeting', 'governance'],
            'Review'         => ['review'],
            'Deployment'     => ['deployment', 'deploy'],
            'Pipeline'       => ['pipeline', 'ai tagging'],
            'Reference'      => ['reference', 'schema', 'tech stack'],
            'Process'        => ['process', 'guide'],
        ];

        // Tag colour palette
        $tagColors = [
            'Onboarding'     => 'bg-rose-500/80 text-white',
            'Taxonomy'       => 'bg-violet-500/80 text-white',
            'Classification' => 'bg-amber-500/80 text-white',
            'Meeting'        => 'bg-sky-500/80 text-white',
            'Review'         => 'bg-indigo-500/80 text-white',
            'Process'        => 'bg-emerald-500/80 text-white',
            'Deployment'     => 'bg-teal-500/80 text-white',
            'Pipeline'       => 'bg-cyan-500/80 text-white',
            'Reference'      => 'bg-fuchsia-500/80 text-white',
            'General'        => 'bg-slate-500/80 text-white',
        ];
        $defaultTagClass = 'bg-slate-500/80 text-white';

        // Helper: derive tag from a note title
        $deriveTag = function (string $title) use ($tagKeywords) {
            $lower = mb_strtolower($title);
            foreach ($tagKeywords as $tag => $keywords) {
                foreach ($keywords as $kw) {
                    if (str_contains($lower, $kw)) return $tag;
                }
            }
            return 'General';
        };

        // Helper: safely extract plain-text content from a Trilium note body
        $extractContent = function (string $triliumUrl, string $noteId) {
            try {
                $contentResp = Http::timeout(3)->get($triliumUrl . '/etapi/notes/' . $noteId . '/content');
                $rawContent = $contentResp->successful() ? $contentResp->body() : '';
            } catch (\Throwable $e) {
                return '';
            }
            if (is_string($rawContent)) {
                $decoded = json_decode($rawContent, true);
                if (is_array($decoded)) {
                    $extracted = $decoded['content'] ?? $decoded['text'] ?? null;
                    if (is_array($extracted) || is_object($extracted)) {
                        $rawContent = json_encode($extracted);
                    } elseif (is_string($extracted)) {
                        $rawContent = $extracted;
                    } else {
                        $rawContent = json_encode($decoded);
                    }
                }
            } else {
                $rawContent = is_scalar($rawContent) ? (string) $rawContent : '';
            }
            $content = mb_substr(strip_tags(strval($rawContent)), 0, 300);
            return is_string($content) ? $content : '';
        };

        $avatarBgs = [
            'bg-gradient-to-br from-indigo-400 to-violet-500',
            'bg-gradient-to-br from-emerald-400 to-teal-500',
            'bg-gradient-to-br from-amber-400 to-orange-500',
            'bg-gradient-to-br from-rose-400 to-pink-500',
            'bg-gradient-to-br from-sky-400 to-blue-500',
        ];

        // Helper: build a note record
        $buildNote = function (array $note, string $content, string $tag, string $tagClass) use (&$notes, $avatarBgs) {
            return [
                'id'        => $note['noteId'],
                'title'     => $note['title'] ?? 'Untitled',
                'author'    => auth()->user()->name ?? 'System',
                'initials'  => auth()->user()->initials ?? 'SY',
                'avatarBg'  => $avatarBgs[count($notes) % count($avatarBgs)],
                'content'   => $content,
                'time'      => \Carbon\Carbon::parse($note['utcDateModified'])->diffForHumans(),
                'tag'       => $tag,
                'tagClass'  => $tagClass,
                'likes'     => DB::table('note_likes')->where('note_id', $note['noteId'])->count(),
                'liked'     => DB::table('note_likes')->where('note_id', $note['noteId'])->where('user_id', auth()->id())->exists(),
                'asset'     => null,
            ];
        };

        try {
            $triliumUrl = config('services.trilium.url');

            // Health check
            $healthStart = microtime(true);
            $healthResp = Http::timeout(5)->get($triliumUrl . '/etapi/notes/root');
            $latency = round((microtime(true) - $healthStart) * 1000);
            if ($healthResp->successful()) {
                $triliumHealth = ['status' => $latency > 2000 ? 'degraded' : 'ok', 'latencyMs' => $latency];
            } else {
                $triliumHealth = ['status' => 'down', 'latencyMs' => $latency];
            }

            // Find the GAM Knowledge Base parent note
            $searchResp = Http::timeout(5)->get($triliumUrl . '/etapi/notes/root');

            if ($searchResp->successful()) {
                $rootChildren = $searchResp->json('childNoteIds', []);

                foreach ($rootChildren as $childId) {
                    if (str_starts_with($childId, '_')) continue;
                    $childResp = Http::timeout(3)->get($triliumUrl . '/etapi/notes/' . $childId);
                    if (!$childResp->successful()) continue;
                    $child = $childResp->json();

                    if (($child['title'] ?? '') === 'GAM Knowledge Base') {
                        $kbChildIds = $child['childNoteIds'] ?? [];

                        // Helper: extract gamTag label from a note's embedded attributes array
                        $readGamTag = function (array $noteData) {
                            foreach ($noteData['attributes'] ?? [] as $attr) {
                                if (($attr['type'] ?? '') === 'label' && ($attr['name'] ?? '') === 'gamTag') {
                                    return $attr['value'] ?? null;
                                }
                            }
                            return null;
                        };

                        // ── Enumerate every direct child of GAM KB ──
                        // Priority: 1) gamTag attribute  2) title keyword matching
                        // Also recurse into children (they inherit parent's tag).
                        foreach ($kbChildIds as $noteId) {
                            $noteResp = Http::timeout(3)->get($triliumUrl . '/etapi/notes/' . $noteId);
                            if (!$noteResp->successful()) continue;
                            $noteData = $noteResp->json();
                            $noteTitle = $noteData['title'] ?? 'Untitled';

                            // Tag resolution: attribute first, then title keywords
                            $attrTag = $readGamTag($noteData);
                            $tag = $attrTag && isset($tagColors[$attrTag]) ? $attrTag : $deriveTag($noteTitle);
                            $tagClass = $tagColors[$tag] ?? $defaultTagClass;
                            $content = $extractContent($triliumUrl, $noteId);

                            $notes[] = $buildNote($noteData, $content, $tag, $tagClass);

                            // If this note has children, include them with the same (parent) tag
                            $subIds = $noteData['childNoteIds'] ?? [];
                            foreach ($subIds as $subId) {
                                $subResp = Http::timeout(3)->get($triliumUrl . '/etapi/notes/' . $subId);
                                if (!$subResp->successful()) continue;
                                $subData = $subResp->json();
                                // Child can override with its own gamTag, else inherit parent's
                                $subAttrTag = $readGamTag($subData);
                                $subTag = $subAttrTag && isset($tagColors[$subAttrTag]) ? $subAttrTag : $tag;
                                $subTagClass = $tagColors[$subTag] ?? $defaultTagClass;
                                $subContent = $extractContent($triliumUrl, $subId);
                                $notes[] = $buildNote($subData, $subContent, $subTag, $subTagClass);
                            }
                        }

                        // Build available tags from what we found + the known palette
                        $seenTags = array_unique(array_column($notes, 'tag'));
                        $allTags = array_unique(array_merge(array_keys($tagColors), $seenTags));
                        $availableTags = array_values(array_map(
                            fn ($t) => ['name' => $t, 'class' => $tagColors[$t] ?? $defaultTagClass],
                            $allTags
                        ));

                        // Sort notes: group by tag alphabetically, General last
                        usort($notes, function ($a, $b) {
                            $aTag = $a['tag'] ?? '';
                            $bTag = $b['tag'] ?? '';
                            if ($aTag === 'General' && $bTag !== 'General') return 1;
                            if ($bTag === 'General' && $aTag !== 'General') return -1;
                            if ($aTag !== $bTag) return strcmp($aTag, $bTag);
                            return 0;
                        });

                        break; // found the KB
                    }
                }
            }
        } catch (\Throwable $e) {
            \Log::warning('Trilium API error: ' . $e->getMessage());
            $triliumError = 'Trilium service is unreachable. Make sure the gam-trilium container is running.';
            $triliumHealth = ['status' => 'down', 'latencyMs' => null];
        }

        // Fetch Trilium note tree with caching
        $noteTree = [];
        try {
            $noteTree = Cache::remember('gam.trilium.tree', 300, function () {
                $triliumUrl = config('services.trilium.url');
                return $this->fetchTriliumSubtree($triliumUrl, 'root', 0, 3);
            });
        } catch (\Throwable $e) {
            \Log::warning('Trilium tree fetch error: ' . $e->getMessage());
        }

        // Fetch team members for # mention autocomplete
        $teamMembers = User::select('id', 'name', 'email')
            ->get()
            ->map(fn ($u) => [
                'id'       => $u->id,
                'name'     => $u->name,
                'email'    => $u->email,
                'initials' => $u->initials ?? strtoupper(substr($u->name, 0, 2)),
            ])
            ->values()
            ->toArray();

        return Inertia::render('Notes', [
            'notes'         => $notes,
            'error'         => $triliumError ?? null,
            'noteTree'      => $noteTree,
            'triliumHealth' => $triliumHealth,
            'teamMembers'   => $teamMembers,
            'availableTags' => $availableTags,
        ]);
    }

    /**
     * Recursively fetch Trilium note subtree.
     */
    private function fetchTriliumSubtree(string $triliumUrl, string $noteId, int $depth, int $maxDepth): array
    {
        if ($depth > $maxDepth) return [];

        $resp = Http::timeout(3)->get($triliumUrl . '/etapi/notes/' . $noteId);
        if (!$resp->successful()) return [];

        $note = $resp->json();
        $children = [];

        foreach (($note['childNoteIds'] ?? []) as $childId) {
            if (str_starts_with($childId, '_')) continue; // skip hidden/system
            $children[] = $this->fetchTriliumSubtree($triliumUrl, $childId, $depth + 1, $maxDepth);
        }

        $typeIconMap = [
            'text'   => 'ri-file-text-line',
            'code'   => 'ri-code-line',
            'image'  => 'ri-image-line',
            'file'   => 'ri-attachment-2',
            'search' => 'ri-search-line',
            'book'   => 'ri-book-line',
        ];

        return [
            'id'       => $note['noteId'] ?? $noteId,
            'title'    => $note['title'] ?? 'Untitled',
            'type'     => $note['type'] ?? 'text',
            'icon'     => $typeIconMap[$note['type'] ?? 'text'] ?? 'ri-file-text-line',
            'children' => array_filter($children),
        ];
    }

    public function datasets(): Response
    {
        return Inertia::render('Datasets', [
            'message' => 'CKAN integration pending — connect via Docker in Phase 7.',
        ]);
    }

    public function preview(Request $request, ?string $id = null): Response
    {
        $asset = null;
        if ($id) {
            $raw = Asset::with(['tags', 'versions', 'uploader', 'reviewer', 'collections'])
                ->find($id);

            if ($raw) {
                $asset = [
                    'id'          => $raw->id,
                    'name'        => $raw->original_filename,
                    'extension'   => $raw->file_extension,
                    'size'        => $raw->file_size_formatted,
                    'sizeBytes'   => $raw->file_size,
                    'mime'        => $raw->mime_type,
                    'hash'        => $raw->sha256_hash,
                    'group'       => $raw->group_classification,
                    'groupColor'  => $raw->group_badge_color,
                    'confidence'  => $raw->group_confidence,
                    'description' => $raw->description,
                    'pipeline'    => $raw->pipeline_status,
                    'preview'     => $raw->preview_status,
                    'review'      => $raw->review_status,
                    'source'      => $raw->upload_source,
                    'uploaderIp'  => $raw->uploader_ip,
                    'ingestedAt'  => $raw->ingested_at?->format('M d, Y H:i'),
                    'uploader'    => $raw->uploader?->name,
                    'reviewer'    => $raw->reviewer?->name,
                    'reviewedAt'  => $raw->reviewed_at?->format('M d, Y H:i'),
                    'tags'        => $raw->tags->map(fn ($t) => [
                        'tag'        => $t->tag,
                        'confidence' => round($t->confidence * 100),
                        'color'      => $t->confidence_color,
                        'approved'   => $t->auto_approved,
                    ]),
                    'versions'    => $raw->versions->map(fn ($v) => [
                        'version' => $v->version_number,
                        'size'    => $v->file_size,
                        'date'    => $v->created_at->format('M d, Y'),
                        'notes'   => $v->change_notes,
                        'user'    => $v->uploader?->name,
                    ]),
                    'collections' => $raw->collections->pluck('name'),
                ];
            }
        }

        return Inertia::render('Preview', [
            'assetId' => $id,
            'asset'   => $asset,
        ]);
    }

    public function profile(Request $request): Response
    {
        $user = $request->user();

        // User's actual permissions
        $userPermissions = $user->getAllPermissions()->pluck('name')->toArray();

        // User's collections
        $userCollections = Collection::where('created_by', $user->id)
            ->withCount('assets')
            ->get()
            ->map(fn ($c) => [
                'name'       => $c->name,
                'assetCount' => $c->assets_count,
                'access'     => $c->access_level,
            ]);

        // Team members — users with the same role
        $teamMembers = User::whereHas('roles', function ($q) use ($user) {
                $q->whereIn('name', $user->getRoleNames());
            })
            ->where('id', '!=', $user->id)
            ->take(5)
            ->get()
            ->map(fn ($u) => [
                'name'     => $u->name,
                'initials' => $u->initials,
                'role'     => $u->primary_role,
            ]);

        return Inertia::render('Profile', [
            'userProfile' => [
                'name'     => $user->name,
                'email'    => $user->email,
                'initials' => $user->initials,
                'role'     => $user->primary_role,
                'joined'   => $user->created_at->format('F j, Y'),
            ],
            'stats' => [
                'uploads'      => $user->assets()->count(),
                'reviews'      => $user->reviewedAssets()->count(),
                'approvalRate' => $user->reviewedAssets()->count() > 0
                    ? round($user->reviewedAssets()->where('review_status', 'approved')->count() / $user->reviewedAssets()->count() * 100, 1)
                    : 0,
                'collections'  => $user->collections()->count(),
                'storageUsed'  => $user->assets()->sum('file_size'),
            ],
            'recentActivity' => Activity::causedBy($user)
                ->latest()
                ->take(6)
                ->get()
                ->map(fn ($a) => [
                    'description' => $a->description,
                    'time'        => $a->created_at->diffForHumans(),
                    'log_name'    => $a->log_name,
                ]),
            'userPermissions'  => $userPermissions,
            'userCollections'  => $userCollections,
            'teamMembers'      => $teamMembers,
        ]);
    }

    // ══════════════════════════════════════════════════════════════
    //  SERVICE HEALTH CHECK (REQ-28: System Health Indicators)
    // ══════════════════════════════════════════════════════════════

    private function checkServiceHealth(): array
    {
        $services = [];

        // MySQL / MariaDB
        try {
            DB::select('SELECT 1');
            $services[] = ['name' => 'MySQL', 'status' => 'up', 'icon' => 'ri-database-2-line'];
        } catch (\Throwable) {
            $services[] = ['name' => 'MySQL', 'status' => 'down', 'icon' => 'ri-database-2-line'];
        }

        // Redis
        try {
            Redis::ping();
            $services[] = ['name' => 'Redis', 'status' => 'up', 'icon' => 'ri-server-line'];
        } catch (\Throwable) {
            $services[] = ['name' => 'Redis', 'status' => 'down', 'icon' => 'ri-server-line'];
        }

        // Meilisearch
        try {
            $meiliHost = config('scout.meilisearch.host', 'http://meilisearch:7700');
            $resp = Http::timeout(2)->get($meiliHost . '/health');
            $services[] = ['name' => 'Meilisearch', 'status' => $resp->successful() ? 'up' : 'down', 'icon' => 'ri-search-eye-line'];
        } catch (\Throwable) {
            $services[] = ['name' => 'Meilisearch', 'status' => 'down', 'icon' => 'ri-search-eye-line'];
        }

        // Horizon (queue worker)
        try {
            $horizonStatus = Cache::get('horizon:status', 'inactive');
            $services[] = ['name' => 'Horizon', 'status' => $horizonStatus === 'running' ? 'up' : 'warn', 'icon' => 'ri-shuffle-line'];
        } catch (\Throwable) {
            $services[] = ['name' => 'Horizon', 'status' => 'down', 'icon' => 'ri-shuffle-line'];
        }

        return $services;
    }

    // ══════════════════════════════════════════════════════════════
    //  AUDIT LOG  (REQ-25: Admin Audit Log)
    // ══════════════════════════════════════════════════════════════

    public function auditLog(Request $request): Response
    {
        $query = Activity::with('causer', 'subject');

        // Filter by action/description keyword
        if ($request->filled('action')) {
            $query->where('description', 'like', '%' . $request->input('action') . '%');
        }

        // Filter by user
        if ($request->filled('user_id')) {
            $query->where('causer_id', $request->input('user_id'));
        }

        // Filter by log name (category)
        if ($request->filled('log_name')) {
            $query->where('log_name', $request->input('log_name'));
        }

        // Date range filter
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->input('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->input('date_to'));
        }

        $activities = $query->latest()
            ->paginate(30)
            ->through(fn ($a) => [
                'id'          => $a->id,
                'description' => $a->description,
                'causer'      => $a->causer?->name ?? 'System',
                'causer_id'   => $a->causer_id,
                'subject_type'=> $a->subject_type ? class_basename($a->subject_type) : null,
                'subject_id'  => $a->subject_id,
                'log_name'    => $a->log_name,
                'properties'  => $a->properties?->toArray(),
                'time'        => $a->created_at->diffForHumans(),
                'date'        => $a->created_at->format('Y-m-d H:i:s'),
            ]);

        // Get distinct log names for filter dropdown
        $logNames = Activity::distinct()->pluck('log_name')->filter()->sort()->values();

        // Get users who have activity for filter dropdown
        $activityUsers = \App\Models\User::whereIn('id', Activity::distinct()->pluck('causer_id')->filter())
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        return Inertia::render('AuditLog', [
            'activities'  => $activities,
            'filters'     => $request->only('action', 'user_id', 'log_name', 'date_from', 'date_to'),
            'logNames'    => $logNames,
            'users'       => $activityUsers,
            'totalCount'  => Activity::count(),
        ]);
    }

    // ══════════════════════════════════════════════════════════════
    //  GOOGLE DRIVE IMPORT  (REQ-04)
    // ══════════════════════════════════════════════════════════════

    public function googleDrive(): Response
    {
        $cfg = config('services.google');
        $configured = !empty($cfg['client_id']) && !empty($cfg['client_secret']);

        $token = null;
        $connected = false;
        $email = null;

        if ($configured) {
            $token = \App\Models\GoogleDriveToken::where('user_id', auth()->id())->first();
            $connected = $token !== null;
            $email = $token?->email;
        }

        return Inertia::render('GoogleDrive', [
            'configured'   => $configured,
            'connected'    => $connected,
            'email'        => $email,
            'totalImports' => Asset::where('source', 'google_drive')->count(),
        ]);
    }

    // ── Helper ─────────────────────────────────────────────────

    private function formatBytesHelper(int $bytes): string
    {
        if ($bytes === 0) return '0 B';
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = floor(log($bytes, 1024));
        return round($bytes / pow(1024, $i), 1) . ' ' . $units[$i];
    }
}
