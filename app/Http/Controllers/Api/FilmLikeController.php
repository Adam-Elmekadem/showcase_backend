<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\FilmResource;
use App\Services\Tmdb\FilmSyncService;
use Illuminate\Http\Request;

class FilmLikeController extends Controller
{
    public function __construct(private readonly FilmSyncService $filmSync) {}

    public function store(Request $request)
    {
        $data = $request->validate([
            'tmdb_id' => ['required', 'integer'],
        ]);

        $film = $this->filmSync->findOrSyncByTmdbId($data['tmdb_id']);

        $request->user()->filmLikes()->firstOrCreate(['film_id' => $film->id]);

        return new FilmResource($film);
    }

    public function destroy(Request $request, int $filmId)
    {
        $request->user()->filmLikes()->where('film_id', $filmId)->delete();

        return response()->noContent();
    }
}
