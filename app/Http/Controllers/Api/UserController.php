<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\FilmResource;
use App\Http\Resources\UserResource;
use App\Models\Film;
use App\Models\User;
use App\Services\Tmdb\FilmSyncService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    private const MAX_FAVORITES = 5;

    public function __construct(private readonly FilmSyncService $filmSync) {}

    public function show(string $username)
    {
        $user = User::where('username', $username)
            ->withCount(['logs', 'lists', 'followers', 'following'])
            ->with('favoriteFilms.film')
            ->firstOrFail();

        return new UserResource($user);
    }

    public function stats(string $username)
    {
        $user = User::where('username', $username)->firstOrFail();

        $filmsWatched = Film::query()
            ->select('films.runtime', 'films.genres')
            ->join('logs', 'logs.film_id', '=', 'films.id')
            ->where('logs.user_id', $user->id)
            ->get();

        return response()->json([
            'data' => [
                'films_logged' => $filmsWatched->count(),
                'hours_watched' => (int) round($filmsWatched->sum('runtime') / 60),
                'average_rating' => round(
                    $user->logs()->whereNotNull('rating_overall')->avg('rating_overall') ?? 0,
                    2
                ),
                'top_genres' => $filmsWatched
                    ->flatMap(fn ($film) => $film->genres ?? [])
                    ->countBy()
                    ->sortDesc()
                    ->take(5)
                    ->keys()
                    ->values(),
            ],
        ]);
    }

    public function watchlist(string $username)
    {
        $user = User::where('username', $username)->firstOrFail();

        $films = $user->watchlistItems()
            ->with('film')
            ->latest()
            ->paginate(24)
            ->through(fn ($item) => $item->film);

        return FilmResource::collection($films);
    }

    public function update(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'username' => ['sometimes', 'string', 'max:32', 'alpha_dash', Rule::unique('users', 'username')->ignore($user->id)],
            'bio' => ['nullable', 'string', 'max:500'],
            'location' => ['nullable', 'string', 'max:120'],
        ]);

        $user->update($data);

        return new UserResource($user->loadCount(['logs', 'lists', 'followers', 'following']));
    }

    public function addFavorite(Request $request)
    {
        $data = $request->validate([
            'tmdb_id' => ['required', 'integer'],
        ]);

        $user = $request->user();
        $film = $this->filmSync->findOrSyncByTmdbId($data['tmdb_id']);

        if (! $user->favoriteFilms()->where('film_id', $film->id)->exists()
            && $user->favoriteFilms()->count() >= self::MAX_FAVORITES) {
            throw ValidationException::withMessages([
                'tmdb_id' => ['You can only pick '.self::MAX_FAVORITES.' favorite films. Remove one first.'],
            ]);
        }

        $user->favoriteFilms()->firstOrCreate(
            ['film_id' => $film->id],
            ['position' => $user->favoriteFilms()->count()]
        );

        return UserResource::make($user->loadCount(['logs', 'lists', 'followers', 'following'])->load('favoriteFilms.film'));
    }

    public function removeFavorite(Request $request, int $filmId)
    {
        $user = $request->user();
        $user->favoriteFilms()->where('film_id', $filmId)->delete();

        $user->favoriteFilms()->orderBy('position')->get()->values()
            ->each(fn ($favorite, $index) => $favorite->update(['position' => $index]));

        return UserResource::make($user->loadCount(['logs', 'lists', 'followers', 'following'])->load('favoriteFilms.film'));
    }
}
