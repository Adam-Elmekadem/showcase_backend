<?php

namespace App\Services\Tmdb;

use App\Models\Film;
use App\Models\FilmCredit;
use App\Models\Person;
use Illuminate\Support\Str;

class FilmSyncService
{
    private const CREDIT_JOB_ROLES = [
        'Director' => 'director',
        'Writer' => 'writer',
        'Screenplay' => 'writer',
        'Director of Photography' => 'cinematographer',
        'Original Music Composer' => 'composer',
    ];

    public function __construct(private readonly TmdbClient $tmdb) {}

    public function findOrSyncByTmdbId(int $tmdbId): Film
    {
        $existing = Film::where('tmdb_id', $tmdbId)->first();
        if ($existing) {
            return $existing;
        }

        return $this->syncByTmdbId($tmdbId);
    }

    public function syncByTmdbId(int $tmdbId): Film
    {
        $details = $this->tmdb->movieDetails($tmdbId);

        $film = Film::updateOrCreate(
            ['tmdb_id' => $details['id']],
            [
                'title' => $details['title'],
                'original_title' => $details['original_title'] ?? null,
                'slug' => $this->uniqueSlug($details['title'], $details['release_date'] ?? null, $details['id']),
                'overview' => $details['overview'] ?? null,
                'poster_path' => $details['poster_path'] ?? null,
                'backdrop_path' => $details['backdrop_path'] ?? null,
                'release_date' => $details['release_date'] ?: null,
                'runtime' => $details['runtime'] ?? null,
                'original_language' => $details['original_language'] ?? null,
                'countries' => collect($details['production_countries'] ?? [])->pluck('name')->all(),
                'genres' => collect($details['genres'] ?? [])->pluck('name')->all(),
            ]
        );

        $this->syncCredits($film, $details['credits'] ?? []);

        return $film->fresh(['credits.person']);
    }

    private function syncCredits(Film $film, array $credits): void
    {
        FilmCredit::where('film_id', $film->id)->delete();

        foreach ($credits['crew'] ?? [] as $crew) {
            $role = self::CREDIT_JOB_ROLES[$crew['job'] ?? ''] ?? null;
            if (! $role) {
                continue;
            }

            $person = $this->findOrSyncPerson($crew);

            FilmCredit::firstOrCreate([
                'film_id' => $film->id,
                'person_id' => $person->id,
                'role' => $role,
                'character' => null,
            ]);
        }

        foreach (array_slice($credits['cast'] ?? [], 0, 20) as $cast) {
            $person = $this->findOrSyncPerson($cast);

            FilmCredit::firstOrCreate([
                'film_id' => $film->id,
                'person_id' => $person->id,
                'role' => 'actor',
                'character' => $cast['character'] ?? null,
            ], [
                'billing_order' => $cast['order'] ?? null,
            ]);
        }
    }

    private function findOrSyncPerson(array $credit): Person
    {
        return Person::firstOrCreate(
            ['tmdb_id' => $credit['id']],
            [
                'name' => $credit['name'],
                'slug' => $this->uniquePersonSlug($credit['name'], $credit['id']),
                'profile_path' => $credit['profile_path'] ?? null,
            ]
        );
    }

    private function uniqueSlug(string $title, ?string $releaseDate, int $tmdbId): string
    {
        $year = $releaseDate ? substr($releaseDate, 0, 4) : null;
        $base = Str::slug($title.($year ? "-{$year}" : ''));
        $base = $base !== '' ? $base : "film-{$tmdbId}";

        $existing = Film::where('tmdb_id', $tmdbId)->first();
        if ($existing && $existing->slug !== null) {
            return $existing->slug;
        }

        $slug = $base;
        $suffix = 2;
        while (Film::where('slug', $slug)->where('tmdb_id', '!=', $tmdbId)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    private function uniquePersonSlug(string $name, int $tmdbId): string
    {
        $base = Str::slug($name);

        return $base !== '' ? $base.'-'.$tmdbId : "person-{$tmdbId}";
    }
}
