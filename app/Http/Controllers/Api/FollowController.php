<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;

class FollowController extends Controller
{
    public function store(Request $request, string $username)
    {
        $target = User::where('username', $username)->firstOrFail();
        abort_if($target->id === $request->user()->id, 422, 'You cannot follow yourself.');

        $request->user()->following()->syncWithoutDetaching([$target->id]);

        return new UserResource($target->loadCount(['followers', 'following']));
    }

    public function destroy(Request $request, string $username)
    {
        $target = User::where('username', $username)->firstOrFail();

        $request->user()->following()->detach($target->id);

        return new UserResource($target->loadCount(['followers', 'following']));
    }

    public function followers(string $username)
    {
        $user = User::where('username', $username)->firstOrFail();

        return UserResource::collection($user->followers()->paginate(30));
    }

    public function following(string $username)
    {
        $user = User::where('username', $username)->firstOrFail();

        return UserResource::collection($user->following()->paginate(30));
    }

    public function mutuals(Request $request)
    {
        return UserResource::collection($request->user()->mutuals()->get());
    }
}
