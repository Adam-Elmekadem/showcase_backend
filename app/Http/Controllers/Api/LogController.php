<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLogRequest;
use App\Http\Resources\LogResource;
use App\Models\LogEntry;
use App\Services\Tmdb\FilmSyncService;
use Illuminate\Http\Request;

class LogController extends Controller
{
    public function __construct(private readonly FilmSyncService $filmSync) {}

    public function index(Request $request)
    {
        $data = $request->validate([
            'username' => ['nullable', 'string'],
            'film_slug' => ['nullable', 'string'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $logs = LogEntry::query()
            ->with(['user', 'film'])
            ->withCount('likes')
            ->when($data['username'] ?? null, fn ($query, $username) => $query->whereHas(
                'user', fn ($q) => $q->where('username', $username)
            ))
            ->when($data['film_slug'] ?? null, fn ($query, $slug) => $query->whereHas(
                'film', fn ($q) => $q->where('slug', $slug)
            ))
            ->latest()
            ->paginate($data['per_page'] ?? 20);

        return LogResource::collection($logs);
    }

    public function store(StoreLogRequest $request)
    {
        $data = $request->validated();

        $film = $this->filmSync->findOrSyncByTmdbId($data['tmdb_id']);

        $log = LogEntry::create([
            'user_id' => $request->user()->id,
            'film_id' => $film->id,
            'watched_on' => $data['watched_on'] ?? null,
            'is_rewatch' => $data['is_rewatch'] ?? false,
            'rating_overall' => $data['rating_overall'] ?? null,
            'rating_story' => $data['rating_story'] ?? null,
            'rating_direction' => $data['rating_direction'] ?? null,
            'rating_acting' => $data['rating_acting'] ?? null,
            'rating_cinematography' => $data['rating_cinematography'] ?? null,
            'rating_music' => $data['rating_music'] ?? null,
            'review' => $data['review'] ?? null,
            'contains_spoilers' => $data['contains_spoilers'] ?? false,
        ]);

        return new LogResource($log->load(['user', 'film']));
    }

    public function update(StoreLogRequest $request, LogEntry $log)
    {
        abort_if($log->user_id !== $request->user()->id, 403);

        $log->update($request->safe()->except('tmdb_id'));

        return new LogResource($log->load(['user', 'film']));
    }

    public function destroy(Request $request, LogEntry $log)
    {
        abort_if($log->user_id !== $request->user()->id, 403);

        $log->delete();

        return response()->noContent();
    }

    public function like(Request $request, LogEntry $log)
    {
        $created = $log->likes()->firstOrCreate(['user_id' => $request->user()->id]);

        if ($created->wasRecentlyCreated) {
            $log->increment('likes_count');
        }

        return new LogResource($log->fresh(['user', 'film']));
    }

    public function unlike(Request $request, LogEntry $log)
    {
        $deleted = $log->likes()->where('user_id', $request->user()->id)->delete();

        if ($deleted) {
            $log->decrement('likes_count');
        }

        return new LogResource($log->fresh(['user', 'film']));
    }
}
