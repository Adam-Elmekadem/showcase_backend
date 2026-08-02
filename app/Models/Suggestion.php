<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Suggestion extends Model
{
    protected $fillable = [
        'sender_id',
        'recipient_id',
        'film_id',
        'film_list_id',
        'message',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
        ];
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }

    public function film(): BelongsTo
    {
        return $this->belongsTo(Film::class);
    }

    public function filmList(): BelongsTo
    {
        return $this->belongsTo(FilmList::class);
    }
}
