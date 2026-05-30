<?php

namespace App\Models;

use App\Enums\GameEventType;
use App\Observers\GameEventObserver;
use Database\Factories\GameEventFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[ObservedBy(GameEventObserver::class)]
class GameEvent extends Model
{
    /** @use HasFactory<GameEventFactory> */
    use HasFactory;

    protected $fillable = [
        'game_id',
        'minute',
        'stoppage',
        'type',
        'team_id',
        'player_id',
        'assist_player_id',
        'secondary_player_id',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'minute' => 'integer',
            'stoppage' => 'integer',
            'type' => GameEventType::class,
        ];
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    /**
     * Assist provider on a goal.
     */
    public function assistPlayer(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'assist_player_id');
    }

    /**
     * For substitutions: the player coming ON (the off player is `player`).
     */
    public function secondaryPlayer(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'secondary_player_id');
    }
}
