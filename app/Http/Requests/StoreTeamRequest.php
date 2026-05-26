<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTeamRequest extends FormRequest
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
        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('teams', 'name')],
            'acronym' => ['required', 'string', 'size:3', 'alpha', 'uppercase', Rule::unique('teams', 'acronym')],
            'year_founded' => ['required', 'integer', 'min:1800', 'max:'.(int) date('Y')],
            'home_ground' => ['nullable', 'string', 'max:255'],
        ];
    }

    protected function prepareForValidation(): void
    {
        // The DB only ever stores uppercase acronyms — coerce eagerly so the
        // user doesn't have to caps-lock to make validation happy.
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
