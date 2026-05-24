<?php

namespace App\Http\Requests;

use App\Models\Season;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSeasonRequest extends FormRequest
{
    public function authorize(): bool
    {
        $season = $this->route('season');

        return $season instanceof Season
            && ($this->user()?->can('update', $season) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $season = $this->route('season');
        $leagueId = $season instanceof Season ? $season->league_id : null;
        $seasonId = $season instanceof Season ? $season->id : null;

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('seasons', 'name')
                    ->ignore($seasonId)
                    ->where(fn ($query) => $query->where('league_id', $leagueId)),
            ],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $season = $this->route('season');
        $default = $season instanceof Season ? $season->is_active : false;

        $this->merge([
            'is_active' => $this->boolean('is_active', $default),
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
