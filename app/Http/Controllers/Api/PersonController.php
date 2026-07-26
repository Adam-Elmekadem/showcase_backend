<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PersonResource;
use App\Models\Person;
use App\Services\Tmdb\TmdbClient;
use Illuminate\Support\Collection;

class PersonController extends Controller
{
    private const CREDIT_JOB_ROLES = [
        'Director' => 'director',
        'Writer' => 'writer',
        'Screenplay' => 'writer',
        'Director of Photography' => 'cinematographer',
        'Original Music Composer' => 'composer',
    ];

    public function __construct(private readonly TmdbClient $tmdb) {}

    public function show(string $slug)
    {
        $person = Person::where('slug', $slug)->firstOrFail();

        $credits = $this->tmdb->personMovieCredits($person->tmdb_id);
        $imageBase = rtrim(config('services.tmdb.image_base_url'), '/');

        $summarize = fn (array $movie, ?string $character = null) => [
            'tmdb_id' => $movie['id'],
            'title' => $movie['title'],
            'release_date' => $movie['release_date'] ?: null,
            'year' => $movie['release_date'] ? substr($movie['release_date'], 0, 4) : null,
            'poster_url' => $movie['poster_path'] ? "{$imageBase}/w342{$movie['poster_path']}" : null,
            'overview' => $movie['overview'] ?? '',
            'vote_average' => $movie['vote_average'] ?? null,
            'character' => $character,
        ];

        $filmography = collect();

        foreach ($credits['crew'] ?? [] as $crew) {
            $role = self::CREDIT_JOB_ROLES[$crew['job'] ?? ''] ?? null;
            if (! $role) {
                continue;
            }
            $filmography->push(['role' => $role, 'film' => $summarize($crew)]);
        }

        foreach ($credits['cast'] ?? [] as $cast) {
            $filmography->push(['role' => 'actor', 'film' => $summarize($cast, $cast['character'] ?? null)]);
        }

        $grouped = $filmography
            ->groupBy('role')
            ->map(function (Collection $entries) {
                return $entries->pluck('film')
                    ->unique('tmdb_id')
                    ->sortByDesc('release_date')
                    ->values();
            });

        return response()->json([
            'data' => (new PersonResource($person))->resolve() + [
                'filmography' => $grouped,
            ],
        ]);
    }
}
