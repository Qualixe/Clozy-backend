<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Media extends Model
{
    protected $fillable = ['path', 'filename', 'mime_type', 'size', 'uploaded_by'];

    public function uploadedBy(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Served through the `/media/file/{filename}` route rather than the
     * `public/storage` symlink — see MediaController::serve() for why.
     */
    public function getUrlAttribute(): string
    {
        return url('/api/media/file/'.basename($this->path));
    }
}
