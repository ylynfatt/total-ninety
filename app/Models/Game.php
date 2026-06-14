<?php

namespace App\Models;

use App\Enums\GameEventType;
use App\Enums\GameStatus;
use App\Observers\GameObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;

#[ObservedBy(GameObserver::class)]
class Game extends Model
{
    use HasFactory;

    protected $fillable = [
        'season_id',
        'stage_id',
        'group_id',
        'home_team_id',
        'away_team_id',
        'round',
        'bracket_position',
        'match_date',
        'location',
        'status',
        'current_minute',
        'clock_started_at',
    ];

    protected function casts(): array
    {
        return [
            'match_date' => 'datetime',
            'status' => GameStatus::class,
            'current_minute' => 'integer',
            'clock_started_at' => 'datetime',
            'round' => 'integer',
            'bracket_position' => 'integer',
        ];
    }

    public function homeTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'home_team_id');
    }

    public function awayTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'away_team_id');
    }

    public function result(): HasOne
    {
        return $this->hasOne(Result::class);
    }

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(Stage::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(GameEvent::class)->orderBy('minute')->orderBy('id');
    }

    /**
     * The timeline ordered by match phase, then minute within the phase.
     *
     * A flat minute sort misplaces the second-half kick-off: its clock snaps
     * back to 45', so it sorts ahead of the first-half stoppage and the Half
     * Time marker (both sitting at 45'+). Walking the events in the order they
     * were recorded, each Kick Off opens a new period; ordering by (period,
     * minute) then keeps Half Time closing the first half and the second-half
     * Kick Off opening the second.
     *
     * Operates on the already-loaded `events` relation so callers keep their
     * eager-loaded team/player names.
     *
     * @return Collection<int, GameEvent>
     */
    public function timelineEvents(): Collection
    {
        $period = 0;
        $periodByEvent = [];

        foreach ($this->events->sortBy('id') as $event) {
            if ($event->type === GameEventType::KickOff) {
                $period++;
            }

            $periodByEvent[$event->id] = max($period, 1);
        }

        return $this->events
            ->sortBy(fn (GameEvent $event): array => [
                $periodByEvent[$event->id],
                $event->minute ?? 0,
                $event->stoppage ?? 0,
                $event->id,
            ])
            ->values();
    }
}
