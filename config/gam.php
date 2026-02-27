<?php

return [

    /*
    |--------------------------------------------------------------------------
    | AI Tagging Configuration
    |--------------------------------------------------------------------------
    */
    'ai' => [
        'model' => env('OPENAI_MODEL', 'openai/gpt-4o-mini'),
        'confidence_threshold' => (float) env('GAM_AI_THRESHOLD', 0.70),
        'max_retries' => 3,
    ],

    /*
    |--------------------------------------------------------------------------
    | Taxonomy Groups
    |--------------------------------------------------------------------------
    */
    // Visual asset groups (7 primary groups from GAM XLSX)
    'groups' => [
        'FOOD'   => 'Farm-to-fork: agriculture, ingredients, packaged goods, grocery, foodservice, restaurants, cooking, beverages',
        'MEDIA'  => 'Media industry: broadcast, streaming, print, digital publishing, social, podcasts, production, talent',
        'GENBUS' => 'General business: meetings, office, factories, retail, transport (work context)',
        'GEO'    => 'Location: US regions, states, cities/markets, skylines, landmarks, place context',
        'NATURE' => 'Nature & environment: landscapes, water, weather, climate, ecosystems, seasons',
        'LIFE'   => 'Lifestyle: family, leisure, sports, travel, hobbies, wellness, home life',
        'SPEC'   => 'Specialty: concepts, icons, diagrams, infographics, patterns, abstract backgrounds',
    ],

    // Document groups (8 doc groups from GAM XLSX)
    'doc_groups' => [
        'DOC-CLIENT' => 'Client deliverables: final/near-final client-facing outputs',
        'DOC-MKT'    => 'Marketing & sales: decks, one-pagers, messaging, proposals',
        'DOC-WEB'    => 'Web / content ops: wireframes, IA, web specs, content plans',
        'DOC-DATA'   => 'Data & analysis: analyses, notebook exports, charts, methodology',
        'DOC-PROD'   => 'Product / BPM: PRDs, alignment docs, workflows',
        'DOC-OPS'    => 'Operations: SOPs, onboarding, checklists',
        'DOC-LEGAL'  => 'Legal / contracts: MSAs, SOWs, NDAs (restricted)',
        'DOC-CLD'    => 'CLDs: logical layer docs/schemas (highly restricted)',
    ],

    /*
    |--------------------------------------------------------------------------
    | Processing Pipeline
    |--------------------------------------------------------------------------
    */
    'pipeline' => [
        'preview_thumbnail_size' => 300,
        'preview_medium_size' => 1200,
        'concurrent_uploads' => 5,
    ],

    /*
    |--------------------------------------------------------------------------
    | Storage
    |--------------------------------------------------------------------------
    */
    'storage' => [
        'staging_disk' => env('GAM_STAGING_DISK', 'staging'),
        'assets_disk' => env('GAM_ASSETS_DISK', 'assets'),
        'previews_disk' => env('GAM_PREVIEWS_DISK', 'previews'),
    ],

    /*
    |--------------------------------------------------------------------------
    | RBAC Group Mapping
    |--------------------------------------------------------------------------
    */
    'role_groups' => [
        'Admin'          => ['FOOD', 'MEDIA', 'GENBUS', 'GEO', 'NATURE', 'LIFE', 'SPEC',
                             'DOC-CLIENT', 'DOC-MKT', 'DOC-WEB', 'DOC-DATA', 'DOC-PROD', 'DOC-OPS', 'DOC-LEGAL', 'DOC-CLD'],
        'Food Team'      => ['FOOD', 'DOC-CLIENT', 'DOC-OPS'],
        'Media Team'     => ['MEDIA', 'DOC-CLIENT', 'DOC-OPS'],
        'Marketing Team' => ['GENBUS', 'LIFE', 'DOC-MKT', 'DOC-WEB'],
    ],
];
