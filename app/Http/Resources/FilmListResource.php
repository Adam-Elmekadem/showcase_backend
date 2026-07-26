<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FilmListResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'tags' => $this->tags,
            'is_ranked' => $this->is_ranked,
            'is_public' => $this->is_public,
            'items_count' => $this->whenCounted('items'),
            'comments_count' => $this->whenCounted('comments'),
            'user' => new UserResource($this->whenLoaded('user')),
            'items' => $this->whenLoaded('items', function () {
                return $this->items->map(fn ($item) => [
                    'id' => $item->id,
                    'position' => $item->position,
                    'note' => $item->note,
                    'film' => new FilmResource($item->film),
                ]);
            }),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
