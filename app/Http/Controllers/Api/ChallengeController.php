<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Challenge;
use App\Models\LogEntry;
use Illuminate\Http\Request;

class ChallengeController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'period' => ['required', 'string', 'in:daily,weekly,monthly,yearly'],
        ]);

        $user = $request->user();

        $challenges = Challenge::where('period', $data['period'])
            ->orderBy('starts_at')
            ->get()
            ->map(function (Challenge $challenge) use ($user) {
                return [
                    'id' => $challenge->id,
                    'title' => $challenge->title,
                    'description' => $challenge->description,
                    'period' => $challenge->period,
                    'starts_at' => $challenge->starts_at->toIso8601String(),
                    'ends_at' => $challenge->ends_at->toIso8601String(),
                    'target' => $challenge->criteria_target,
                    'progress' => $this->progressFor($challenge, $user),
                ];
            });

        return response()->json(['data' => $challenges]);
    }

    /**
     * Computed on the fly from the viewer's logs rather than stored -- always
     * accurate, no sync step needed when a log is created/edited/deleted.
     */
    private function progressFor(Challenge $challenge, $user): int
    {
        $query = LogEntry::where('user_id', $user->id)
            ->whereBetween('watched_on', [$challenge->starts_at, $challenge->ends_at]);

        if ($challenge->criteria_type === 'genre_watch_count' && $challenge->criteria_genre) {
            $query->whereHas('film', fn ($q) => $q->whereJsonContains('genres', $challenge->criteria_genre));
        }

        return $query->count();
    }
}
