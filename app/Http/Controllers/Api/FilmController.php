<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\FilmListResource;
use App\Http\Resources\FilmResource;
use App\Http\Resources\LogResource;
use App\Models\Film;
use App\Models\FilmList;
use App\Models\LogEntry;
use App\Services\Tmdb\FilmSyncService;
use App\Services\Tmdb\TmdbClient;
use Illuminate\Http\Request;

class FilmController extends Controller
{
    private const SORT_OPTIONS = [
        'popularity.desc',
        'popularity.asc',
        'vote_average.desc',
        'vote_average.asc',
        'primary_release_date.desc',
        'primary_release_date.asc',
        'title.asc',
    ];

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

        return response()->json([
            'data' => $this->mapSummaries($results),
        ]);
    }

    public function discover(Request $request)
    {
        $data = $request->validate([
            'genre' => ['nullable', 'integer'],
            'year' => ['nullable', 'integer', 'min:1888', 'max:2100'],
            'min_rating' => ['nullable', 'numeric', 'min:0', 'max:10'],
            'sort_by' => ['nullable', 'string', 'in:'.implode(',', self::SORT_OPTIONS)],
            'page' => ['nullable', 'integer', 'min:1', 'max:500'],
        ]);

        $params = array_filter([
            'with_genres' => $data['genre'] ?? null,
            'primary_release_year' => $data['year'] ?? null,
            'vote_average.gte' => $data['min_rating'] ?? null,
            'sort_by' => $data['sort_by'] ?? 'popularity.desc',
            'vote_count.gte' => 20,
        ], fn ($value) => $value !== null);

        $response = $this->tmdb->discoverMovies($params, $data['page'] ?? 1);

        return response()->json([
            'data' => $this->mapSummaries($response['results'] ?? []),
            'meta' => [
                'page' => $response['page'] ?? 1,
                'total_pages' => $response['total_pages'] ?? 1,
            ],
        ]);
    }

    public function genres()
    {
        return response()->json(['data' => $this->tmdb->genreList()]);
    }

    public function show(string $slug)
    {
        $film = Film::where('slug', $slug)->firstOrFail();
        $film->load(['directors', 'people']);
        $film->loadCount('comments');

        return new FilmResource($film);
    }

    public function showcases(string $slug)
    {
        $film = Film::where('slug', $slug)->firstOrFail();

        $showcases = FilmList::query()
            ->where('is_public', true)
            ->whereHas('items', fn ($query) => $query->where('film_id', $film->id))
            ->with('user')
            ->withCount('items')
            ->latest()
            ->limit(12)
            ->get();

        return FilmListResource::collection($showcases);
    }

    public function myShowcases(Request $request, string $slug)
    {
        $film = Film::where('slug', $slug)->firstOrFail();

        $showcases = FilmList::where('user_id', $request->user()->id)
            ->withCount('items')
            ->latest()
            ->get()
            ->map(function (FilmList $list) use ($film, $request) {
                $data = (new FilmListResource($list))->resolve($request);
                $data['contains_film'] = $list->items()->where('film_id', $film->id)->exists();

                return $data;
            });

        return response()->json(['data' => $showcases]);
    }

    public function friendsActivity(Request $request, string $slug)
    {
        $film = Film::where('slug', $slug)->firstOrFail();
        $followingIds = $request->user()->following()->pluck('users.id');

        $logs = LogEntry::query()
            ->where('film_id', $film->id)
            ->whereIn('user_id', $followingIds)
            ->with('user')
            ->latest()
            ->limit(10)
            ->get();

        return LogResource::collection($logs);
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

    private function mapSummaries(array $movies): array
    {
        $imageBase = rtrim(config('services.tmdb.image_base_url'), '/');
        $genreNames = collect($this->tmdb->genreList())->pluck('name', 'id');

        return collect($movies)->map(fn ($movie) => [
            'tmdb_id' => $movie['id'],
            'title' => $movie['title'],
            'release_date' => $movie['release_date'] ?: null,
            'year' => $movie['release_date'] ? substr($movie['release_date'], 0, 4) : null,
            'poster_url' => $movie['poster_path'] ? "{$imageBase}/w342{$movie['poster_path']}" : null,
            'overview' => $movie['overview'],
            'vote_average' => $movie['vote_average'] ?? null,
            'genres' => collect($movie['genre_ids'] ?? [])->map(fn ($id) => $genreNames->get($id))->filter()->values()->all(),
        ])->values()->all();
    }
}
