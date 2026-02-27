<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookstack_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('bookstack_page_id');
            $table->string('page_title')->nullable();
            $table->string('page_url')->nullable();
            $table->timestamps();

            $table->unique(['asset_id', 'bookstack_page_id']);
        });

        Schema::create('trilium_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->string('trilium_note_id', 50);
            $table->string('note_title')->nullable();
            $table->timestamps();

            $table->unique(['asset_id', 'trilium_note_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trilium_links');
        Schema::dropIfExists('bookstack_links');
    }
};
