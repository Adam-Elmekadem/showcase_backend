<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SuggestionResource extends JsonResource
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
            'message' => $this->message,
            'read_at' => $this->read_at?->toIso8601String(),
            'sender' => new UserResource($this->whenLoaded('sender')),
            'film' => new FilmResource($this->whenLoaded('film')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
