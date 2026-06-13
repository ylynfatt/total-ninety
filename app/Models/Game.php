<?php

namespace App\Models;

use App\Enums\GameStatus;
use App\Observers\GameObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

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
}
