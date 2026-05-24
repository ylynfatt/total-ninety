<?php

namespace App\Http\Requests;

use App\Models\League;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSeasonRequest extends FormRequest
{
    public function authorize(): bool
    {
        $league = $this->route('league');

        return $league instanceof League
            && ($this->user()?->can('update', $league) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $league = $this->route('league');
        $leagueId = $league instanceof League ? $league->id : null;

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('seasons', 'name')->where(fn ($query) => $query->where('league_id', $leagueId)),
            ],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active', false),
        ]);
    }

    public function messages(): array
    {
        return [
            'name.unique' => 'A season with that name already exists in this league.',
            'ends_on.after_or_equal' => 'End date must be on or after the start date.',
        ];
    }
}
