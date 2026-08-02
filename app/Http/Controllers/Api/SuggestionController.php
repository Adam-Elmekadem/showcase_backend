<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\SuggestionResource;
use App\Models\FilmList;
use App\Models\Suggestion;
use App\Models\User;
use App\Services\Tmdb\FilmSyncService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SuggestionController extends Controller
{
    public function __construct(private readonly FilmSyncService $filmSync) {}

    public function index(Request $request)
    {
        $suggestions = $request->user()
            ->suggestionsReceived()
            ->with(['sender', 'film', 'filmList'])
            ->latest()
            ->paginate(20);

        return SuggestionResource::collection($suggestions);
    }

    public function store(Request $request, string $username)
    {
        $recipient = User::where('username', $username)->firstOrFail();
        $sender = $request->user();

        abort_if($recipient->id === $sender->id, 422, 'You cannot send a suggestion to yourself.');

        if (! $sender->isMutualWith($recipient)) {
            throw ValidationException::withMessages([
                'username' => ['You can only send suggestions to people who follow you back.'],
            ]);
        }

        $data = $request->validate([
            'tmdb_id' => ['required_without:list_id', 'integer'],
            'list_id' => ['required_without:tmdb_id', 'integer'],
            'message' => ['nullable', 'string', 'max:500'],
        ]);

        $attributes = [
            'sender_id' => $sender->id,
            'recipient_id' => $recipient->id,
            'message' => $data['message'] ?? null,
        ];

        if (isset($data['tmdb_id'])) {
            $film = $this->filmSync->findOrSyncByTmdbId($data['tmdb_id']);
            $attributes['film_id'] = $film->id;
        } else {
            $list = FilmList::findOrFail($data['list_id']);
            abort_if(! $list->is_public && $list->user_id !== $sender->id, 403, 'This showcase is private.');
            $attributes['film_list_id'] = $list->id;
        }

        $suggestion = Suggestion::create($attributes);

        return new SuggestionResource($suggestion->load(['sender', 'film', 'filmList']));
    }

    public function destroy(Request $request, Suggestion $suggestion)
    {
        abort_if($suggestion->recipient_id !== $request->user()->id, 403);

        $suggestion->delete();

        return response()->noContent();
    }

    public function markRead(Request $request, Suggestion $suggestion)
    {
        abort_if($suggestion->recipient_id !== $request->user()->id, 403);

        $suggestion->update(['read_at' => now()]);

        return new SuggestionResource($suggestion->load(['sender', 'film']));
    }
}
