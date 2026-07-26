<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\SuggestionResource;
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
            ->with(['sender', 'film'])
            ->latest()
            ->paginate(20);

        return SuggestionResource::collection($suggestions);
    }

    public function store(Request $request, string $username)
    {
        $recipient = User::where('username', $username)->firstOrFail();
        $sender = $request->user();

        abort_if($recipient->id === $sender->id, 422, 'You cannot suggest a film to yourself.');

        if (! $sender->isMutualWith($recipient)) {
            throw ValidationException::withMessages([
                'username' => ['You can only suggest films to people who follow you back.'],
            ]);
        }

        $data = $request->validate([
            'tmdb_id' => ['required', 'integer'],
            'message' => ['nullable', 'string', 'max:500'],
        ]);

        $film = $this->filmSync->findOrSyncByTmdbId($data['tmdb_id']);

        $suggestion = Suggestion::create([
            'sender_id' => $sender->id,
            'recipient_id' => $recipient->id,
            'film_id' => $film->id,
            'message' => $data['message'] ?? null,
        ]);

        return new SuggestionResource($suggestion->load(['sender', 'film']));
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
