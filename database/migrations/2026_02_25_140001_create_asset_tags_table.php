<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->string('tag', 100);
            $table->decimal('confidence', 5, 2)->default(0);
            $table->boolean('auto_approved')->default(false);
            $table->boolean('is_manual')->default(false); // human-added tag
            $table->foreignId('added_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['asset_id', 'auto_approved']);
            $table->index('tag');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_tags');
    }
};
