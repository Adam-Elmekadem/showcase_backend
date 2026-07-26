<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ListItem extends Model
{
    protected $fillable = [
        'film_list_id',
        'film_id',
        'position',
        'note',
    ];

    public function list(): BelongsTo
    {
        return $this->belongsTo(FilmList::class, 'film_list_id');
    }

    public function film(): BelongsTo
    {
        return $this->belongsTo(Film::class);
    }
}
