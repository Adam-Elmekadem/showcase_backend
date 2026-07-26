<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FilmResource extends JsonResource
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
            'title' => $this->title,
            'original_title' => $this->original_title,
            'slug' => $this->slug,
            'overview' => $this->overview,
            'poster_url' => $this->poster_path ? "{$imageBase}/w500{$this->poster_path}" : null,
            'backdrop_url' => $this->backdrop_path ? "{$imageBase}/original{$this->backdrop_path}" : null,
            'release_date' => $this->release_date?->toDateString(),
            'year' => $this->release_date?->year,
            'runtime' => $this->runtime,
            'original_language' => $this->original_language,
            'countries' => $this->countries,
            'genres' => $this->genres,
            'vote_average' => $this->vote_average !== null ? (float) $this->vote_average : null,
            'directors' => PersonResource::collection($this->whenLoaded('directors')),
            'credits' => $this->when($this->relationLoaded('people'), function () {
                return $this->people->groupBy('pivot.role')->map(
                    fn ($people) => PersonResource::collection($people)
                );
            }),
        ];
    }
}
