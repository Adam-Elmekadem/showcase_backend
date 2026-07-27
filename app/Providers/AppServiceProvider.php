<?php

namespace App\Providers;

use App\Models\Comment;
use App\Models\Film;
use App\Models\FilmList;
use App\Models\LogEntry;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Relation::morphMap([
            'film' => Film::class,
            'showcase' => FilmList::class,
            'log' => LogEntry::class,
            'comment' => Comment::class,
        ]);
    }
}
