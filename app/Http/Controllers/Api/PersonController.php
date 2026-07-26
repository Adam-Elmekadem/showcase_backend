<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\FilmResource;
use App\Http\Resources\PersonResource;
use App\Models\Person;

class PersonController extends Controller
{
    public function show(string $slug)
    {
        $person = Person::where('slug', $slug)->firstOrFail();

        $filmography = $person->films()
            ->withPivot(['role', 'character', 'billing_order'])
            ->orderByDesc('release_date')
            ->get()
            ->groupBy('pivot.role');

        return response()->json([
            'data' => (new PersonResource($person))->resolve() + [
                'filmography' => $filmography->map(
                    fn ($films) => FilmResource::collection($films)
                ),
            ],
        ]);
    }
}
