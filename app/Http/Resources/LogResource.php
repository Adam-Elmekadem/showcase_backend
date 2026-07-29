<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LogResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // See UserResource for why this needs the explicit 'sanctum' guard.
        $viewer = $request->user('sanctum');

        return [
            'id' => $this->id,
            'watched_on' => $this->watched_on?->toDateString(),
            'is_rewatch' => $this->is_rewatch,
            'ratings' => [
                'overall' => $this->rating_overall,
                'story' => $this->rating_story,
                'direction' => $this->rating_direction,
                'acting' => $this->rating_acting,
                'cinematography' => $this->rating_cinematography,
                'music' => $this->rating_music,
            ],
            'review' => $this->review,
            'quote' => $this->quote,
            'contains_spoilers' => $this->contains_spoilers,
            'likes_count' => $this->likes_count,
            'liked_by_viewer' => $this->when($viewer !== null, function () use ($viewer) {
                return $this->relationLoaded('likes')
                    ? $this->likes->contains('user_id', $viewer->id)
                    : $this->likes()->where('user_id', $viewer->id)->exists();
            }),
            'comments_count' => $this->comments_count,
            'user' => new UserResource($this->whenLoaded('user')),
            'film' => new FilmResource($this->whenLoaded('film')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
