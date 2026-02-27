<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->string('original_filename');
            $table->text('original_path')->nullable();
            $table->string('file_extension', 20)->nullable();
            $table->unsignedBigInteger('file_size')->default(0);
            $table->string('mime_type', 100)->nullable();
            $table->string('sha256_hash', 64)->nullable()->index();
            $table->string('upload_source', 30)->default('direct_upload'); // direct_upload, google_drive, api_import
            $table->string('uploader_ip', 45)->nullable();
            $table->timestamp('ingested_at')->nullable();

            // Classification
            $table->string('group_classification', 30)->nullable(); // Food, Media, Business, Location, Nature, Lifestyle, Specialty
            $table->decimal('group_confidence', 5, 2)->nullable();
            $table->text('description')->nullable();

            // Processing status
            $table->string('pipeline_status', 30)->default('queued'); // queued, hashing, previewing, tagging, classifying, indexing, syncing, done, failed
            $table->string('preview_status', 20)->default('pending'); // pending, processing, done, failed
            $table->string('review_status', 20)->default('none'); // none, pending, approved, rejected
            $table->text('review_reason')->nullable();

            // Master/derived linking
            $table->boolean('is_master')->default(true);
            $table->foreignId('derived_from')->nullable()->constrained('assets')->nullOnDelete();

            // Storage paths
            $table->string('storage_disk', 30)->default('assets');
            $table->string('storage_path')->nullable();
            $table->string('preview_path')->nullable();
            $table->string('thumbnail_path')->nullable();

            // Relationships
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};
