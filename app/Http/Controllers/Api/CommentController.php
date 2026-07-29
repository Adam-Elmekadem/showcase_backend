<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CommentResource;
use App\Models\Comment;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CommentController extends Controller
{
    private const TYPES = ['film', 'showcase', 'log'];

    public function index(Request $request)
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(self::TYPES)],
            'id' => ['required', 'integer'],
        ]);

        $comments = Comment::query()
            ->where('commentable_type', $data['type'])
            ->where('commentable_id', $data['id'])
            ->whereNull('parent_id')
            ->with(['user', 'replies'])
            ->latest()
            ->get();

        return CommentResource::collection($comments);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(self::TYPES)],
            'id' => ['required', 'integer'],
            'body' => ['required', 'string', 'max:2000'],
            'parent_id' => ['nullable', 'integer', 'exists:comments,id'],
        ]);

        $modelClass = Relation::getMorphedModel($data['type']);
        $target = $modelClass::findOrFail($data['id']);

        if (! empty($data['parent_id'])) {
            $parent = Comment::findOrFail($data['parent_id']);
            $sameThread = $parent->commentable_type === $data['type'] && $parent->commentable_id === $data['id'];
            abort_if(! $sameThread, 422, 'That reply does not belong to this thread.');
        }

        $comment = Comment::create([
            'user_id' => $request->user()->id,
            'commentable_type' => $data['type'],
            'commentable_id' => $target->id,
            'parent_id' => $data['parent_id'] ?? null,
            'body' => $data['body'],
        ]);

        $this->notifyForComment($request, $comment, $target, $parent ?? null);
        $this->notifyMentions($request, $comment);

        return new CommentResource($comment->load('user'));
    }

    public function destroy(Request $request, Comment $comment)
    {
        abort_if($comment->user_id !== $request->user()->id, 403);

        $comment->delete();

        return response()->noContent();
    }

    /**
     * Replies notify the parent comment's author. Top-level comments notify
     * the commentable's owner, if it has one -- films have no single owner,
     * so a top-level comment on a film notifies no one.
     */
    private function notifyForComment(Request $request, Comment $comment, $target, ?Comment $parent): void
    {
        $actorId = $request->user()->id;
        $recipientId = null;

        if ($parent) {
            if ($parent->user_id !== $actorId) {
                $recipientId = $parent->user_id;
            }
        } elseif (isset($target->user_id) && $target->user_id !== $actorId) {
            $recipientId = $target->user_id;
        }

        if ($recipientId) {
            Notification::create([
                'user_id' => $recipientId,
                'actor_id' => $actorId,
                'type' => 'comment',
                'notifiable_type' => 'comment',
                'notifiable_id' => $comment->id,
            ]);
        }
    }

    /**
     * Comment bodies carry mention tokens like "@[Name](user:username)".
     * Any mentioned platform user (other than the author) gets notified;
     * person mentions ("person:slug") are just links, not accounts.
     */
    private function notifyMentions(Request $request, Comment $comment): void
    {
        preg_match_all('/@\[[^\]]+\]\(user:([a-zA-Z0-9_.-]+)\)/', $comment->body, $matches);
        $usernames = array_unique($matches[1] ?? []);

        if (empty($usernames)) {
            return;
        }

        $actorId = $request->user()->id;

        User::whereIn('username', $usernames)
            ->where('id', '!=', $actorId)
            ->pluck('id')
            ->each(function (int $userId) use ($actorId, $comment) {
                Notification::create([
                    'user_id' => $userId,
                    'actor_id' => $actorId,
                    'type' => 'mention',
                    'notifiable_type' => 'comment',
                    'notifiable_id' => $comment->id,
                ]);
            });
    }
}
