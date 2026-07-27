<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use App\Models\Comment;
use App\Models\FilmList;
use App\Models\LogEntry;
use App\Models\Notification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $notifications = $request->user()
            ->notifications()
            ->with('actor')
            ->with(['notifiable' => function ($morphTo) {
                $morphTo->morphWith([
                    LogEntry::class => ['film'],
                    Comment::class => ['commentable'],
                ]);
            }])
            ->latest()
            ->paginate(20);

        foreach ($notifications as $notification) {
            if ($notification->notifiable_type !== 'comment' || ! $notification->notifiable) {
                continue;
            }

            $commentable = $notification->notifiable->commentable;

            if ($commentable instanceof LogEntry) {
                $commentable->loadMissing('film');
            } elseif ($commentable instanceof FilmList) {
                $commentable->loadMissing('user');
            }
        }

        return NotificationResource::collection($notifications);
    }

    public function markRead(Request $request, Notification $notification)
    {
        abort_if($notification->user_id !== $request->user()->id, 403);

        $notification->update(['read_at' => now()]);

        return new NotificationResource($notification->load('actor'));
    }

    public function markAllRead(Request $request)
    {
        $request->user()->notifications()->whereNull('read_at')->update(['read_at' => now()]);

        return response()->noContent();
    }
}
