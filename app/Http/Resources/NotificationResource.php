<?php

namespace App\Http\Resources;

use App\Models\Film;
use App\Models\FilmList;
use App\Models\LogEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'actor' => new UserResource($this->whenLoaded('actor')),
            'link' => $this->resolveLink(),
            'preview' => $this->resolvePreview(),
            'read_at' => $this->read_at?->toISOString(),
            'created_at' => $this->created_at->toISOString(),
        ];
    }

    private function resolveLink(): ?string
    {
        $target = $this->notifiable;

        if ($this->notifiable_type === 'log' && $target instanceof LogEntry && $target->film) {
            return "/films/{$target->film->slug}";
        }

        if ($this->notifiable_type === 'comment' && $target) {
            $commentable = $target->commentable;

            if ($commentable instanceof Film) {
                return "/films/{$commentable->slug}";
            }
            if ($commentable instanceof LogEntry && $commentable->film) {
                return "/films/{$commentable->film->slug}";
            }
            if ($commentable instanceof FilmList && $commentable->user) {
                return "/showcases/{$commentable->user->username}/{$commentable->slug}";
            }
        }

        return null;
    }

    private function resolvePreview(): ?string
    {
        if ($this->notifiable_type === 'log') {
            return $this->notifiable?->film?->title;
        }

        if ($this->notifiable_type === 'comment') {
            return $this->notifiable?->body;
        }

        return null;
    }
}
