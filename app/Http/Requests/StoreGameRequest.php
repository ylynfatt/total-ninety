<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreGameRequest extends FormRequest
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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'home_team' => ['required', 'integer', 'exists:teams,id'],
            'away_team' => ['required', 'integer', 'exists:teams,id', 'different:home_team'],
            'match_date' => ['required', 'date', 'after:today'],
            'location' => ['required', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'away_team.different' => 'The away team must be different from the home team.',
            'match_date.after' => 'The match date must be in the future.',
            'home_team.exists' => 'The selected home team does not exist.',
            'away_team.exists' => 'The selected away team does not exist.',
        ];
    }
}
