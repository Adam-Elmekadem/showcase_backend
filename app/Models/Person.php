<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Person extends Model
{
    protected $fillable = [
        'tmdb_id',
        'name',
        'slug',
        'biography',
        'profile_path',
        'birthday',
        'place_of_birth',
    ];

    protected function casts(): array
    {
        return [
            'birthday' => 'date',
        ];
    }

    public function films(): BelongsToMany
    {
        return $this->belongsToMany(Film::class, 'film_credits')
            ->withPivot(['role', 'character', 'billing_order']);
    }
}
