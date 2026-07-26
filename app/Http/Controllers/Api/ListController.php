<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFilmListRequest;
use App\Http\Resources\FilmListResource;
use App\Models\FilmList;
use App\Services\Tmdb\FilmSyncService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ListController extends Controller
{
    public function __construct(private readonly FilmSyncService $filmSync) {}

    public function index(Request $request)
    {
        $data = $request->validate([
            'username' => ['nullable', 'string'],
        ]);

        $lists = FilmList::query()
            ->with(['user', 'items' => fn ($query) => $query->orderBy('position')->limit(3)->with('film')])
            ->withCount('items')
            ->when(! ($data['username'] ?? null), fn ($query) => $query->where('is_public', true))
            ->when($data['username'] ?? null, fn ($query, $username) => $query->whereHas(
                'user', fn ($q) => $q->where('username', $username)
            ))
            ->latest()
            ->paginate(20);

        return FilmListResource::collection($lists);
    }

    public function show(string $username, string $slug)
    {
        $list = FilmList::query()
            ->whereHas('user', fn ($q) => $q->where('username', $username))
            ->where('slug', $slug)
            ->with(['user', 'items.film'])
            ->firstOrFail();

        return new FilmListResource($list);
    }

    public function store(StoreFilmListRequest $request)
    {
        $data = $request->validated();

        $list = FilmList::create([
            'user_id' => $request->user()->id,
            'name' => $data['name'],
            'slug' => $this->uniqueSlug($request->user()->id, $data['name']),
            'description' => $data['description'] ?? null,
            'tags' => $data['tags'] ?? null,
            'is_ranked' => $data['is_ranked'] ?? false,
            'is_public' => $data['is_public'] ?? true,
        ]);

        return new FilmListResource($list->load('user'));
    }

    public function update(StoreFilmListRequest $request, FilmList $list)
    {
        abort_if($list->user_id !== $request->user()->id, 403);

        $list->update($request->validated());

        return new FilmListResource($list->load('user'));
    }

    public function destroy(Request $request, FilmList $list)
    {
        abort_if($list->user_id !== $request->user()->id, 403);

        $list->delete();

        return response()->noContent();
    }

    public function addItem(Request $request, FilmList $list)
    {
        abort_if($list->user_id !== $request->user()->id, 403);

        $data = $request->validate([
            'tmdb_id' => ['required', 'integer'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $film = $this->filmSync->findOrSyncByTmdbId($data['tmdb_id']);

        $list->items()->updateOrCreate(
            ['film_id' => $film->id],
            [
                'note' => $data['note'] ?? null,
                'position' => $list->items()->max('position') + 1,
            ]
        );

        return new FilmListResource($list->fresh(['user', 'items.film']));
    }

    public function removeItem(Request $request, FilmList $list, int $filmId)
    {
        abort_if($list->user_id !== $request->user()->id, 403);

        $list->items()->where('film_id', $filmId)->delete();

        return new FilmListResource($list->fresh(['user', 'items.film']));
    }

    private function uniqueSlug(int $userId, string $name): string
    {
        $base = Str::slug($name) ?: 'list';
        $slug = $base;
        $suffix = 2;

        while (FilmList::where('user_id', $userId)->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
