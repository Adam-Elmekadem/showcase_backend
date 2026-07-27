<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class LogEntry extends Model
{
    protected $table = 'logs';

    protected $fillable = [
        'user_id',
        'film_id',
        'watched_on',
        'is_rewatch',
        'rating_overall',
        'rating_story',
        'rating_direction',
        'rating_acting',
        'rating_cinematography',
        'rating_music',
        'review',
        'quote',
        'contains_spoilers',
    ];

    protected function casts(): array
    {
        return [
            'watched_on' => 'date',
            'is_rewatch' => 'boolean',
            'contains_spoilers' => 'boolean',
            'rating_overall' => 'float',
            'rating_story' => 'float',
            'rating_direction' => 'float',
            'rating_acting' => 'float',
            'rating_cinematography' => 'float',
            'rating_music' => 'float',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function film(): BelongsTo
    {
        return $this->belongsTo(Film::class);
    }

    public function likes(): HasMany
    {
        return $this->hasMany(LogLike::class, 'log_id');
    }

    public function comments(): MorphMany
    {
        return $this->morphMany(Comment::class, 'commentable');
    }
}
