<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FilmList extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'slug',
        'description',
        'tags',
        'is_ranked',
        'is_public',
    ];

    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'is_ranked' => 'boolean',
            'is_public' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ListItem::class)->orderBy('position');
    }
}
