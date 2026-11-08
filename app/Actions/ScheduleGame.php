<?php

namespace App\Actions;

use App\Enums\GameStatus;
use App\Models\Game;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class ScheduleGame
{
    /** @param array{match_date?: string|null, location?: string|null} $attributes */
    public function execute(Game $game, array $attributes): void
    {
        try {
            Cache::lock('fixture-scheduling', 30)->block(5, function () use ($game, $attributes): void {
                $game->getConnection()->transaction(function () use ($game, $attributes): void {
                    $game->refresh();
                    $kickoff = array_key_exists('match_date', $attributes) ? $attributes['match_date'] : $game->match_date;
                    $location = array_key_exists('location', $attributes) ? $attributes['location'] : $game->location;
                    $location = filled($location) ? trim($location) : null;

                    if ($kickoff !== null) {
                        $kickoff = Carbon::parse($kickoff, config('app.timezone'))->setTimezone(config('app.timezone'));
                        $this->assertAvailable($game, $kickoff, $location);
                    }

                    $game->update([...$attributes, 'match_date' => $kickoff, 'location' => $location]);
                });
            });
        } catch (LockTimeoutException) {
            throw ValidationException::withMessages(['match_date' => 'Another schedule edit is in progress. Please try again.']);
        }
    }

    private function assertAvailable(Game $game, Carbon $kickoff, ?string $location): void
    {
        $minutes = (int) config('games.schedule_window_minutes');
        $teamIds = array_filter([$game->home_team_id, $game->away_team_id]);
        $conflicts = Game::query()->whereKeyNot($game->id)
            ->whereNotIn('status', [GameStatus::Cancelled, GameStatus::Postponed])
            ->where('match_date', '>', $kickoff->copy()->subMinutes($minutes))
            ->where('match_date', '<', $kickoff->copy()->addMinutes($minutes));

        if ((clone $conflicts)->where(fn (Builder $query) => $query->whereIn('home_team_id', $teamIds)->orWhereIn('away_team_id', $teamIds))->exists()) {
            throw ValidationException::withMessages(['match_date' => "A team already has a match within this {$minutes}-minute fixture window. Choose another kickoff."]);
        }

        if ($location !== null && $conflicts->whereRaw('LOWER(TRIM(location)) = ?', [mb_strtolower($location)])->exists()) {
            throw ValidationException::withMessages(['location' => "This venue is already booked within this {$minutes}-minute fixture window. Choose another venue or kickoff."]);
        }
    }
}
