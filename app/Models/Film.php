<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Film extends Model
{
    /** @use HasFactory<\Database\Factories\FilmFactory> */
    use HasFactory;

    protected $fillable = [
        'tmdb_id',
        'title',
        'original_title',
        'slug',
        'overview',
        'poster_path',
        'backdrop_path',
        'release_date',
        'runtime',
        'original_language',
        'countries',
        'genres',
        'vote_average',
    ];

    protected function casts(): array
    {
        return [
            'release_date' => 'date',
            'countries' => 'array',
            'genres' => 'array',
        ];
    }

    public function credits(): HasMany
    {
        return $this->hasMany(FilmCredit::class);
    }

    public function people(): BelongsToMany
    {
        return $this->belongsToMany(Person::class, 'film_credits')
            ->withPivot(['role', 'character', 'billing_order']);
    }

    public function directors(): BelongsToMany
    {
        return $this->people()->wherePivot('role', 'director');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(LogEntry::class);
    }

    public function comments(): MorphMany
    {
        return $this->morphMany(Comment::class, 'commentable');
    }
}
