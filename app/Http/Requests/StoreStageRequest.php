<?php

namespace App\Http\Requests;

use App\Enums\StageFormat;
use App\Models\Season;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class StoreStageRequest extends FormRequest
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
        $seasonId = $season instanceof Season ? $season->id : null;

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('stages', 'name')->where(fn ($q) => $q->where('season_id', $seasonId)),
            ],
            'format' => ['required', new Enum(StageFormat::class)],
            'order' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'advances_count' => ['nullable', 'integer', 'min:1'],
            'config' => ['nullable', 'array'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'order' => $this->input('order', 0),
        ]);
    }

    public function messages(): array
    {
        return [
            'name.unique' => 'A stage with that name already exists in this season.',
            'ends_on.after_or_equal' => 'End date must be on or after the start date.',
        ];
    }
}
