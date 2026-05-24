<?php

namespace App\Http\Requests;

use App\Models\Stage;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStageRequest extends FormRequest
{
    public function authorize(): bool
    {
        $stage = $this->route('stage');

        return $stage instanceof Stage
            && ($this->user()?->can('update', $stage) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $stage = $this->route('stage');
        $seasonId = $stage instanceof Stage ? $stage->season_id : null;
        $stageId = $stage instanceof Stage ? $stage->id : null;

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('stages', 'name')
                    ->ignore($stageId)
                    ->where(fn ($q) => $q->where('season_id', $seasonId)),
            ],
            // Format is intentionally not editable post-creation: changing it
            // would invalidate any already-generated fixtures and groups.
            'order' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'advances_count' => ['nullable', 'integer', 'min:1'],
            'config' => ['nullable', 'array'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function validated($key = null, $default = null)
    {
        // The format column stays whatever it was when the stage was created;
        // we strip it from any incoming payload defensively.
        $data = parent::validated();
        unset($data['format']);

        return $key === null ? $data : ($data[$key] ?? $default);
    }

    public function messages(): array
    {
        return [
            'name.unique' => 'A stage with that name already exists in this season.',
            'ends_on.after_or_equal' => 'End date must be on or after the start date.',
        ];
    }
}
