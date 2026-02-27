<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('taxonomy_rules', function (Blueprint $table) {
            $table->id();
            $table->string('raw_term', 100);
            $table->string('canonical_term', 100);
            $table->string('group_hint', 30)->nullable(); // Food, Media, Business, etc.
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('raw_term');
            $table->index('canonical_term');
            $table->unique(['raw_term', 'canonical_term']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('taxonomy_rules');
    }
};
