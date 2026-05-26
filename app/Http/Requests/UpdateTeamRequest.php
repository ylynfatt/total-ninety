<?php

namespace App\Http\Requests;

use App\Models\Team;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTeamRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $team = $this->route('team');
        $teamId = $team instanceof Team ? $team->id : null;

        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('teams', 'name')->ignore($teamId)],
            'acronym' => ['required', 'string', 'size:3', 'alpha', 'uppercase', Rule::unique('teams', 'acronym')->ignore($teamId)],
            'year_founded' => ['required', 'integer', 'min:1800', 'max:'.(int) date('Y')],
            'home_ground' => ['nullable', 'string', 'max:255'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('acronym') && is_string($this->input('acronym'))) {
            $this->merge(['acronym' => strtoupper(trim($this->input('acronym')))]);
        }
    }

    public function messages(): array
    {
        return [
            'acronym.size' => 'The acronym must be exactly 3 letters.',
            'acronym.alpha' => 'The acronym can only contain letters.',
            'name.unique' => 'A team with that name already exists.',
            'acronym.unique' => 'A team with that acronym already exists.',
            'year_founded.min' => 'The founding year must be 1800 or later.',
            'year_founded.max' => 'The founding year cannot be in the future.',
        ];
    }
}
