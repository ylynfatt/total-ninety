<?php

namespace App\Http\Requests;

use App\Domain\Formats\FormatRegistry;
use App\Enums\StageFormat;
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
            'format' => [
                'sometimes',
                Rule::prohibitedIf(! $this->canReplaceFormat()),
                Rule::in(array_map(fn (StageFormat $format): string => $format->value, app(FormatRegistry::class)->supportedFormats())),
            ],
            'order' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'advances_count' => ['nullable', 'integer', 'min:1'],
            'config' => ['nullable', 'array'],
            'config.legs_per_group' => ['sometimes', 'integer', 'in:1,2'],
            'config.best_placed_count' => ['sometimes', 'integer', 'min:1', 'max:16'],
            'config.entrants' => ['sometimes', 'array', StoreStageRequest::entrantsRule()],
        ];
    }

    protected function prepareForValidation(): void
    {
        $stage = $this->route('stage');
        $format = $this->input('format', $stage instanceof Stage ? $stage->format->value : null);

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

        if (! empty($config['entrants']) && is_array($config['entrants']) && in_array($format, ['single_elimination', 'double_elimination'], true)) {
            $config['entrants'] = StoreStageRequest::normalizeEntrants($config['entrants']);
        } else {
            unset($config['entrants']);
        }

        $this->merge([
            'config' => empty($config) ? null : $config,
        ]);
    }

    private function canReplaceFormat(): bool
    {
        $stage = $this->route('stage');

        return $stage instanceof Stage && ! app(FormatRegistry::class)->supports($stage->format)
            && ! $stage->games()->exists() && ! $stage->groups()->exists();
    }

    public function messages(): array
    {
        return [
            'format.prohibited' => 'The format is locked after setup. Only an empty unsupported stage can change format.',
            'format.in' => 'Choose a supported competition format.',
            'name.unique' => 'A stage with that name already exists in this season.',
            'ends_on.after_or_equal' => 'End date must be on or after the start date.',
            'config.legs_per_group.in' => 'Legs per group must be either 1 (single round-robin) or 2 (home and away).',
            'config.best_placed_count.min' => 'Best-placed qualifiers must be at least 1 (leave it blank for none).',
            'config.best_placed_count.max' => 'Best-placed qualifiers cannot exceed 16.',
        ];
    }
}
