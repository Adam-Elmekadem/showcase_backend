<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FilmCredit;
use App\Models\Person;
use App\Models\User;
use Illuminate\Http\Request;

class MentionController extends Controller
{
    public function search(Request $request)
    {
        $data = $request->validate([
            'q' => ['required', 'string', 'min:1', 'max:50'],
            'film_id' => ['nullable', 'integer'],
        ]);

        $q = $data['q'];
        $filmId = $data['film_id'] ?? null;
        $imageBase = rtrim(config('services.tmdb.image_base_url'), '/');

        $users = User::query()
            ->where(fn ($query) => $query->where('name', 'like', "%{$q}%")->orWhere('username', 'like', "%{$q}%"))
            ->limit(6)
            ->get(['username', 'name', 'avatar_path'])
            ->map(fn ($user) => [
                'username' => $user->username,
                'name' => $user->name,
                'avatar_url' => $user->avatar_path,
            ]);

        $rolesByPersonId = collect();
        if ($filmId) {
            $rolesByPersonId = FilmCredit::where('film_id', $filmId)->pluck('role', 'person_id');
        }

        $people = Person::query()
            ->where('name', 'like', "%{$q}%")
            ->limit(6)
            ->get(['id', 'slug', 'name', 'profile_path'])
            ->map(fn ($person) => [
                'slug' => $person->slug,
                'name' => $person->name,
                'profile_url' => $person->profile_path ? "{$imageBase}/w185{$person->profile_path}" : null,
                'role' => $rolesByPersonId->get($person->id),
            ]);

        return response()->json([
            'data' => [
                'users' => $users,
                'people' => $people,
            ],
        ]);
    }
}
