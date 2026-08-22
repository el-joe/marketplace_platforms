<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FileHash extends Model
{
    protected $table = 'files_hashes';

    public function file(): BelongsTo
    {
        return $this->belongsTo(File::class);
    }
}
