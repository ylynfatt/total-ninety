<?php

namespace App\Models;

use App\Enums\StageFormat;
use Database\Factories\StageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Stage extends Model
{
    /** @use HasFactory<StageFactory> */
    use HasFactory;

    protected $fillable = [
        'season_id',
        'name',
        'order',
        'format',
        'starts_on',
        'ends_on',
        'advances_count',
        'config',
    ];

    protected function casts(): array
    {
        return [
            'order' => 'integer',
            'format' => StageFormat::class,
            'starts_on' => 'date',
            'ends_on' => 'date',
            'advances_count' => 'integer',
            'config' => 'array',
        ];
    }

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    public function groups(): HasMany
    {
        return $this->hasMany(Group::class)->orderBy('order');
    }

    public function games(): HasMany
    {
        return $this->hasMany(Game::class);
    }

    /**
     * The grouped stage that feeds this one — the nearest earlier stage in
     * the same season (by order, then id) whose format has groups. Used to
     * resolve knockout entrant slots ("Winner Group A") against real
     * standings. Null when no grouped stage precedes this one.
     */
    public function previousGroupedStage(): ?Stage
    {
        return self::query()
            ->where('season_id', $this->season_id)
            ->where(fn ($q) => $q
                ->where('order', '<', $this->order)
                ->orWhere(fn ($q2) => $q2->where('order', $this->order)->where('id', '<', $this->id)))
            ->whereIn('format', [StageFormat::GroupStage->value, StageFormat::Conference->value])
            ->orderByDesc('order')
            ->orderByDesc('id')
            ->first();
    }
}
