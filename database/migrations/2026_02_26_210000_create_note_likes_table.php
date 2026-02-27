<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('note_likes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('note_id', 64);  // Trilium note ID
            $table->timestamps();
            $table->unique(['user_id', 'note_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('note_likes');
    }
};
