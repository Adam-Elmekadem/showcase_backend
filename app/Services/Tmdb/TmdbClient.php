<?php

namespace App\Services\Tmdb;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

class TmdbClient
{
    private string $baseUrl;

    private string $key;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.tmdb.base_url'), '/');
        $this->key = (string) config('services.tmdb.key');
    }

    public function searchMovies(string $query, int $page = 1): array
    {
        return $this->get('/search/movie', [
            'query' => $query,
            'page' => $page,
            'include_adult' => false,
        ])['results'] ?? [];
    }

    public function movieDetails(int $tmdbId): array
    {
        return Cache::remember("tmdb:movie:{$tmdbId}", now()->addDay(), function () use ($tmdbId) {
            return $this->get("/movie/{$tmdbId}", [
                'append_to_response' => 'credits',
            ]);
        });
    }

    public function personDetails(int $tmdbId): array
    {
        return Cache::remember("tmdb:person:{$tmdbId}", now()->addDay(), function () use ($tmdbId) {
            return $this->get("/person/{$tmdbId}");
        });
    }

    private function get(string $path, array $query = []): array
    {
        if ($this->key === '') {
            throw new RuntimeException('TMDB_API_KEY is not configured. Add it to your .env file.');
        }

        $response = Http::withToken($this->key)
            ->acceptJson()
            ->get($this->baseUrl.$path, $query);

        $response->throw();

        return $response->json();
    }
}
