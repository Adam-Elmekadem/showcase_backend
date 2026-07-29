<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Explicit 'sanctum' guard so this resolves the Bearer-token viewer even on
        // routes that aren't behind the auth:sanctum middleware (e.g. the public
        // show/search endpoints) — $request->user() alone only works when the
        // route itself is gated, since it falls back to the session-based 'web'
        // guard otherwise and is always null for a token-only API request.
        $viewer = $request->user('sanctum');

        return [
            'id' => $this->id,
            'name' => $this->name,
            'username' => $this->username,
            'bio' => $this->bio,
            'location' => $this->location,
            'avatar_url' => $this->avatar_path,
            'member_since' => $this->created_at?->toDateString(),
            'watchlist_is_public' => (bool) $this->watchlist_is_public,
            'stats' => $this->when(
                $this->logs_count !== null || $this->lists_count !== null,
                fn () => [
                    'films_logged' => $this->logs_count ?? 0,
                    'lists' => $this->lists_count ?? 0,
                    'followers' => $this->followers_count ?? 0,
                    'following' => $this->following_count ?? 0,
                ]
            ),
            'is_followed_by_viewer' => $this->when($viewer !== null && $viewer->id !== $this->id, function () use ($viewer) {
                return $viewer->following()->where('followee_id', $this->id)->exists();
            }),
            'favorite_films' => $this->whenLoaded('favoriteFilms', function () {
                return FilmResource::collection($this->favoriteFilms->pluck('film'));
            }),
        ];
    }
}
