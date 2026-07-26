<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LogLike extends Model
{
    protected $fillable = [
        'log_id',
        'user_id',
    ];

    public function log(): BelongsTo
    {
        return $this->belongsTo(LogEntry::class, 'log_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
