<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PersonResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $imageBase = rtrim(config('services.tmdb.image_base_url'), '/');

        return [
            'id' => $this->id,
            'tmdb_id' => $this->tmdb_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'biography' => $this->when($this->biography !== null, $this->biography),
            'profile_url' => $this->profile_path ? "{$imageBase}/w300{$this->profile_path}" : null,
            'birthday' => $this->birthday?->toDateString(),
            'place_of_birth' => $this->place_of_birth,
            'role' => $this->when(isset($this->pivot), fn () => $this->pivot->role),
            'character' => $this->when(isset($this->pivot) && $this->pivot->character, fn () => $this->pivot->character),
        ];
    }
}
