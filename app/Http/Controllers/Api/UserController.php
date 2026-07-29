<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\FilmResource;
use App\Http\Resources\UserResource;
use App\Models\Film;
use App\Models\User;
use App\Services\Tmdb\FilmSyncService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    private const MAX_FAVORITES = 5;

    public function __construct(private readonly FilmSyncService $filmSync) {}

    public function search(Request $request)
    {
        $data = $request->validate([
            'q' => ['required', 'string', 'min:1', 'max:50'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $viewer = $request->user();

        $users = User::query()
            ->where(fn ($query) => $query
                ->where('name', 'like', "%{$data['q']}%")
                ->orWhere('username', 'like', "%{$data['q']}%"))
            ->when($viewer, fn ($query) => $query->where('id', '!=', $viewer->id))
            ->withCount(['followers', 'following'])
            ->orderBy('name')
            ->paginate($data['per_page'] ?? 20);

        return UserResource::collection($users);
    }

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

    public function watchlist(Request $request, string $username)
    {
        $user = User::where('username', $username)->firstOrFail();

        $viewer = $request->user('sanctum');
        abort_if(
            ! $user->watchlist_is_public && $viewer?->id !== $user->id,
            403,
            'This watchlist is private.'
        );

        $data = $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $films = $user->watchlistItems()
            ->with('film')
            ->latest()
            ->paginate($data['per_page'] ?? 24)
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
            'watchlist_is_public' => ['sometimes', 'boolean'],
        ]);

        $user->update($data);

        return new UserResource($user->loadCount(['logs', 'lists', 'followers', 'following']));
    }

    public function updateAvatar(Request $request)
    {
        $data = $request->validate([
            'avatar' => ['required', 'image', 'max:5120'],
        ]);

        $user = $request->user();

        // Clean up the previous avatar if it was one we stored ourselves
        // (skip anything that isn't one of our own /storage URLs).
        $publicUrlPrefix = rtrim(config('app.url'), '/').'/storage/';
        if ($user->avatar_path && str_starts_with($user->avatar_path, $publicUrlPrefix)) {
            Storage::disk('public')->delete(str_replace($publicUrlPrefix, '', $user->avatar_path));
        }

        $path = $request->file('avatar')->store('avatars', 'public');
        $user->update(['avatar_path' => $publicUrlPrefix.$path]);

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
