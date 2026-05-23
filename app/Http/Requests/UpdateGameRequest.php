<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateGameRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'home_team' => ['required', 'integer', 'exists:teams,id'],
            'away_team' => ['required', 'integer', 'exists:teams,id', 'different:home_team'],
            'match_date' => ['required', 'date'],
            'location' => ['required', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'away_team.different' => 'The away team must be different from the home team.',
            'home_team.exists' => 'The selected home team does not exist.',
            'away_team.exists' => 'The selected away team does not exist.',
        ];
    }
}
