<?php

namespace App\Http\Requests;

use App\Enums\GameEventType;
use App\Models\Game;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class UpdateGameEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        $game = $this->route('game');

        return $game instanceof Game
            && ($this->user()?->can('update', $game) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $scoringTypes = [
            GameEventType::Goal->value,
            GameEventType::OwnGoal->value,
            GameEventType::PenaltyGoal->value,
        ];

        return [
            'type' => ['required', new Enum(GameEventType::class)],
            'minute' => ['nullable', 'integer', 'min:0', 'max:200'],
            'stoppage' => ['nullable', 'integer', 'min:0', 'max:30'],
            // A scoring event must name the team it counts for, otherwise the
            // scoreline can't be adjusted.
            'team_id' => [Rule::requiredIf(fn (): bool => in_array($this->input('type'), $scoringTypes, true)), 'nullable', 'integer', 'exists:teams,id'],
            'player_id' => ['nullable', 'integer', 'exists:players,id'],
            'assist_player_id' => ['nullable', 'integer', 'exists:players,id'],
            'secondary_player_id' => ['nullable', 'integer', 'exists:players,id'],
            'description' => ['nullable', 'string', 'max:500'],
        ];
    }
}
