<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\Film;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function show(string $username)
    {
        $user = User::where('username', $username)
            ->withCount(['logs', 'lists', 'followers', 'following'])
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
}
