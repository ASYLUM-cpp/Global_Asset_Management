<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Taxonomy Terms — Controlled vocabulary loaded from the GAM XLSX spreadsheet.
 *
 * Stores all 7 primary groups, 8 doc groups, 18 project tag packs,
 * ~300+ visual keywords, ~338 doc keywords, 23 VIZ keywords, and 68 geo states.
 * Used by AiTagAsset to build DeepSeek prompts and by ApplyTaxonomy to validate tags.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('taxonomy_terms', function (Blueprint $table) {
            $table->id();
            $table->string('term_type', 40);              // primary_group, doc_group, project, keyword, keyword_category, doc_keyword, viz_keyword, geo_state
            $table->string('group_code', 40);              // FOOD, MEDIA, GENBUS, DOC-CLIENT, VIZ, GEO, etc.
            $table->string('facet', 80)->nullable();        // category facet (e.g., "Vertical", "BPM Dimension", "Food Subject")
            $table->string('term_code', 80)->nullable();    // machine code (FOOD-AG, DOC_CLIENT_VERTICAL_FOOD, VIZ-CT-BAR, AL)
            $table->string('term_label', 150);              // human label (Agriculture, Food, Bar Chart, Alabama)
            $table->string('parent_code', 80)->nullable();  // hierarchical parent (FOOD, FOOD_A, DOC-CLIENT, etc.)
            $table->text('description')->nullable();
            $table->json('extra')->nullable();               // overflow data (file_types, access_level, synonyms, etc.)
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('term_type');
            $table->index('group_code');
            $table->index('term_code');
            $table->index(['term_type', 'group_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('taxonomy_terms');
    }
};
