<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTeamRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'acronym' => ['required', 'string', 'size:3', 'uppercase'],
            'year_founded' => ['required', 'integer', 'min:1800', 'max:'.date('Y')],
            'home_ground' => ['required', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'acronym.size' => 'The acronym must be exactly 3 characters.',
            'acronym.uppercase' => 'The acronym must be uppercase.',
            'year_founded.min' => 'The founding year must be after 1800.',
            'year_founded.max' => 'The founding year cannot be in the future.',
        ];
    }
}
