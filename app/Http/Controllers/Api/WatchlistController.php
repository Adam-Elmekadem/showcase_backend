<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\FilmResource;
use App\Services\Tmdb\FilmSyncService;
use Illuminate\Http\Request;

class WatchlistController extends Controller
{
    public function __construct(private readonly FilmSyncService $filmSync) {}

    public function index(Request $request)
    {
        $data = $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $films = $request->user()
            ->watchlistItems()
            ->with('film')
            ->latest()
            ->paginate($data['per_page'] ?? 24)
            ->through(fn ($item) => $item->film);

        return FilmResource::collection($films);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'tmdb_id' => ['required', 'integer'],
        ]);

        $film = $this->filmSync->findOrSyncByTmdbId($data['tmdb_id']);

        $request->user()->watchlistItems()->firstOrCreate(['film_id' => $film->id]);

        return new FilmResource($film);
    }

    public function destroy(Request $request, int $filmId)
    {
        $request->user()->watchlistItems()->where('film_id', $filmId)->delete();

        return response()->noContent();
    }

    public function clear(Request $request)
    {
        $request->user()->watchlistItems()->delete();

        return response()->noContent();
    }
}
