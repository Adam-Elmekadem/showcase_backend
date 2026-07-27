<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLogRequest;
use App\Http\Resources\LogResource;
use App\Models\LogEntry;
use App\Models\Notification;
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
            'following' => ['nullable', 'boolean'],
        ]);

        $viewer = $request->user('sanctum');
        $followingOnly = $request->boolean('following');

        $logs = LogEntry::query()
            ->with(['user', 'film'])
            ->withCount(['likes', 'comments'])
            ->when($data['username'] ?? null, fn ($query, $username) => $query->whereHas(
                'user', fn ($q) => $q->where('username', $username)
            ))
            ->when($data['film_slug'] ?? null, fn ($query, $slug) => $query->whereHas(
                'film', fn ($q) => $q->where('slug', $slug)
            ))
            ->when($followingOnly, fn ($query) => $query->whereIn(
                'user_id', $viewer ? $viewer->following()->pluck('users.id') : []
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
            'rating_overall' => $this->averageRating($data),
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

        $data = $request->safe()->except('tmdb_id');

        // Partial updates may omit aspect fields entirely; fall back to the
        // log's current value for any that weren't sent in this request.
        $effective = [];
        foreach (self::ASPECT_FIELDS as $field) {
            $effective[$field] = array_key_exists($field, $data) ? $data[$field] : $log->$field;
        }
        $data['rating_overall'] = $this->averageRating($effective);

        $log->update($data);

        return new LogResource($log->load(['user', 'film']));
    }

    private const ASPECT_FIELDS = [
        'rating_story',
        'rating_direction',
        'rating_acting',
        'rating_cinematography',
        'rating_music',
    ];

    /**
     * The overall rating is never set directly by the client -- it is the
     * average of whichever aspect ratings (story/direction/acting/
     * cinematography/music) were actually filled in, so a log with only
     * two aspects rated doesn't get dragged down by unrated ones.
     */
    private function averageRating(array $data): ?float
    {
        $aspects = collect(self::ASPECT_FIELDS)
            ->map(fn ($field) => $data[$field] ?? null)
            ->filter(fn ($value) => $value !== null);

        if ($aspects->isEmpty()) {
            return null;
        }

        return round($aspects->average() * 2) / 2;
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

            if ($log->user_id !== $request->user()->id) {
                Notification::create([
                    'user_id' => $log->user_id,
                    'actor_id' => $request->user()->id,
                    'type' => 'like',
                    'notifiable_type' => 'log',
                    'notifiable_id' => $log->id,
                ]);
            }
        }

        return new LogResource($log->fresh(['user', 'film'])->loadCount('comments'));
    }

    public function unlike(Request $request, LogEntry $log)
    {
        $deleted = $log->likes()->where('user_id', $request->user()->id)->delete();

        if ($deleted) {
            $log->decrement('likes_count');
        }

        return new LogResource($log->fresh(['user', 'film'])->loadCount('comments'));
    }
}
