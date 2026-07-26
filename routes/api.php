<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\FilmController;
use App\Http\Controllers\Api\FollowController;
use App\Http\Controllers\Api\ListController;
use App\Http\Controllers\Api\LogController;
use App\Http\Controllers\Api\PersonController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\WatchlistController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);

Route::get('/films/search', [FilmController::class, 'search']);
Route::get('/films/discover', [FilmController::class, 'discover']);
Route::get('/films/genres', [FilmController::class, 'genres']);
Route::post('/films/sync', [FilmController::class, 'sync']);
Route::get('/films/{slug}', [FilmController::class, 'show']);
Route::get('/films/{slug}/showcases', [FilmController::class, 'showcases']);

Route::get('/people/{slug}', [PersonController::class, 'show']);

Route::get('/logs', [LogController::class, 'index']);
Route::get('/lists', [ListController::class, 'index']);
Route::get('/lists/{username}/{slug}', [ListController::class, 'show']);

Route::get('/users/{username}', [UserController::class, 'show']);
Route::get('/users/{username}/stats', [UserController::class, 'stats']);
Route::get('/users/{username}/watchlist', [UserController::class, 'watchlist']);
Route::get('/users/{username}/followers', [FollowController::class, 'followers']);
Route::get('/users/{username}/following', [FollowController::class, 'following']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::patch('/me', [UserController::class, 'update']);
    Route::post('/me/favorites', [UserController::class, 'addFavorite']);
    Route::delete('/me/favorites/{filmId}', [UserController::class, 'removeFavorite']);

    Route::get('/films/{slug}/friends-activity', [FilmController::class, 'friendsActivity']);

    Route::post('/logs', [LogController::class, 'store']);
    Route::patch('/logs/{log}', [LogController::class, 'update']);
    Route::delete('/logs/{log}', [LogController::class, 'destroy']);
    Route::post('/logs/{log}/like', [LogController::class, 'like']);
    Route::delete('/logs/{log}/like', [LogController::class, 'unlike']);

    Route::post('/lists', [ListController::class, 'store']);
    Route::patch('/lists/{list}', [ListController::class, 'update']);
    Route::delete('/lists/{list}', [ListController::class, 'destroy']);
    Route::post('/lists/{list}/items', [ListController::class, 'addItem']);
    Route::delete('/lists/{list}/items/{filmId}', [ListController::class, 'removeItem']);

    Route::get('/me/watchlist', [WatchlistController::class, 'index']);
    Route::post('/watchlist', [WatchlistController::class, 'store']);
    Route::delete('/watchlist/{filmId}', [WatchlistController::class, 'destroy']);

    Route::post('/users/{username}/follow', [FollowController::class, 'store']);
    Route::delete('/users/{username}/follow', [FollowController::class, 'destroy']);
});
