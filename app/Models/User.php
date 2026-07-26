<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'bio',
        'location',
        'avatar_path',
        'watchlist_is_public',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'watchlist_is_public' => 'boolean',
        ];
    }

    public function logs(): HasMany
    {
        return $this->hasMany(LogEntry::class);
    }

    public function lists(): HasMany
    {
        return $this->hasMany(FilmList::class);
    }

    public function watchlistItems(): HasMany
    {
        return $this->hasMany(WatchlistItem::class);
    }

    public function favoriteFilms(): HasMany
    {
        return $this->hasMany(FavoriteFilm::class)->orderBy('position');
    }

    public function suggestionsReceived(): HasMany
    {
        return $this->hasMany(Suggestion::class, 'recipient_id');
    }

    public function suggestionsSent(): HasMany
    {
        return $this->hasMany(Suggestion::class, 'sender_id');
    }

    public function following(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'follows', 'follower_id', 'followee_id')->withTimestamps();
    }

    public function followers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'follows', 'followee_id', 'follower_id')->withTimestamps();
    }

    /**
     * Users this user follows who also follow back -- the pool suggestions
     * can be sent to.
     */
    public function mutuals(): BelongsToMany
    {
        return $this->following()->whereIn('users.id', $this->followers()->pluck('users.id'));
    }

    public function isMutualWith(User $other): bool
    {
        return $this->following()->where('users.id', $other->id)->exists()
            && $this->followers()->where('users.id', $other->id)->exists();
    }
}
