<?php

namespace App\Http\Requests;

use App\Enums\GameStatus;
use App\Models\Game;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreResultRequest extends FormRequest
{
    public function authorize(): bool
    {
        $game = $this->route('game');

        // Recording a result requires the same league-ownership gate as
        // updating the game itself.
        return $game instanceof Game
            && ($this->user()?->can('update', $game) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'home_team_score' => ['required', 'integer', 'min:0', 'max:999'],
            'away_team_score' => ['required', 'integer', 'min:0', 'max:999'],
            'winner_side' => ['nullable', Rule::in(['home', 'away'])],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $game = $this->route('game');
            $isBracket = $game->stage?->format->isBracket() ?? false;
            $isDraw = $this->integer('home_team_score') === $this->integer('away_team_score');
            $winner = $this->input('winner_side');

            if ($winner !== null && (! $isBracket || ! $isDraw || $game->home_team_id === null || $game->away_team_id === null)) {
                $validator->errors()->add('winner_side', 'Select a winning side only for a drawn knockout match with both teams assigned.');
            }

            if ($isBracket && $isDraw && in_array($game->status, [GameStatus::Scheduled, GameStatus::Postponed, GameStatus::FullTime], true) && $winner === null) {
                $validator->errors()->add('winner_side', 'Select the winning side to resolve this drawn knockout match.');
            }
        }];
    }

    public function messages(): array
    {
        return ['winner_side.in' => 'Choose the home or away team as the winning side.'];
    }
}
