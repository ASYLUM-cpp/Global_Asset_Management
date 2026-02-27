<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staging_assets', function (Blueprint $table) {
            $table->id();
            $table->string('original_filename');
            $table->string('file_extension', 20)->nullable();
            $table->unsignedBigInteger('file_size')->default(0);
            $table->string('mime_type', 100)->nullable();
            $table->string('storage_path');
            $table->string('upload_source', 30)->default('direct_upload');
            $table->string('uploader_ip', 45)->nullable();
            $table->string('status', 20)->default('pending'); // pending, processing, completed, failed
            $table->text('error_message')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('asset_id')->nullable()->constrained()->nullOnDelete(); // linked after processing
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staging_assets');
    }
};
