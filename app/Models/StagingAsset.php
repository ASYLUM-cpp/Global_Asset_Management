<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StagingAsset extends Model
{
    protected $fillable = [
        'original_filename',
        'file_extension',
        'file_size',
        'mime_type',
        'storage_path',
        'upload_source',
        'uploader_ip',
        'status',
        'error_message',
        'uploaded_by',
        'asset_id',
    ];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
        ];
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }
}
