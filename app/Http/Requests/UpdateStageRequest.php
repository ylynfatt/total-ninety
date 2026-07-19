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
            'config.legs_per_group' => ['sometimes', 'integer', 'in:1,2'],
            'config.best_placed_count' => ['sometimes', 'integer', 'min:1', 'max:16'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $stage = $this->route('stage');
        $format = $stage instanceof Stage ? $stage->format->value : null;

        $config = $this->input('config', []);
        if (! is_array($config)) {
            $config = [];
        }

        if (isset($config['legs_per_group']) && $format === 'group_stage') {
            $config['legs_per_group'] = (int) $config['legs_per_group'];
        } else {
            unset($config['legs_per_group']);
        }

        if (! empty($config['best_placed_count']) && in_array($format, ['group_stage', 'conference'], true)) {
            $config['best_placed_count'] = (int) $config['best_placed_count'];
        } else {
            unset($config['best_placed_count']);
        }

        $this->merge([
            'config' => empty($config) ? null : $config,
        ]);
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
            'config.legs_per_group.in' => 'Legs per group must be either 1 (single round-robin) or 2 (home and away).',
            'config.best_placed_count.min' => 'Best-placed qualifiers must be at least 1 (leave it blank for none).',
            'config.best_placed_count.max' => 'Best-placed qualifiers cannot exceed 16.',
        ];
    }
}
