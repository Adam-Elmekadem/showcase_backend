<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\FilmResource;
use App\Models\Film;
use App\Services\Tmdb\FilmSyncService;
use App\Services\Tmdb\TmdbClient;
use Illuminate\Http\Request;

class FilmController extends Controller
{
    public function __construct(
        private readonly TmdbClient $tmdb,
        private readonly FilmSyncService $filmSync,
    ) {}

    public function search(Request $request)
    {
        $data = $request->validate([
            'q' => ['required', 'string', 'min:1'],
        ]);

        $results = $this->tmdb->searchMovies($data['q']);
        $imageBase = rtrim(config('services.tmdb.image_base_url'), '/');

        return response()->json([
            'data' => collect($results)->map(fn ($movie) => [
                'tmdb_id' => $movie['id'],
                'title' => $movie['title'],
                'release_date' => $movie['release_date'] ?: null,
                'year' => $movie['release_date'] ? substr($movie['release_date'], 0, 4) : null,
                'poster_url' => $movie['poster_path'] ? "{$imageBase}/w342{$movie['poster_path']}" : null,
                'overview' => $movie['overview'],
            ])->values(),
        ]);
    }

    public function show(string $slug)
    {
        $film = Film::where('slug', $slug)->firstOrFail();
        $film->load(['directors', 'people']);

        return new FilmResource($film);
    }

    public function sync(Request $request)
    {
        $data = $request->validate([
            'tmdb_id' => ['required', 'integer'],
        ]);

        $film = $this->filmSync->findOrSyncByTmdbId($data['tmdb_id']);
        $film->load(['directors', 'people']);

        return new FilmResource($film);
    }
}
