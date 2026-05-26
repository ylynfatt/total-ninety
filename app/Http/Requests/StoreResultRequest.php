<?php

namespace App\Http\Requests;

use App\Models\Game;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

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
        ];
    }
}
