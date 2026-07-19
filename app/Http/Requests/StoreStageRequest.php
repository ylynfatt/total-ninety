<?php

namespace App\Http\Requests;

use App\Domain\Formats\EntrantSlot;
use App\Enums\StageFormat;
use App\Models\Season;
use Closure;
use DomainException;
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
            'config.legs_per_group' => ['sometimes', 'integer', 'in:1,2'],
            'config.best_placed_count' => ['sometimes', 'integer', 'min:1', 'max:16'],
            'config.entrants' => ['sometimes', 'array', self::entrantsRule()],
        ];
    }

    /**
     * Shared shape check for config.entrants: a power-of-two-sized list of
     * valid slot descriptors (see EntrantSlot).
     */
    public static function entrantsRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (! is_array($value)) {
                return;
            }

            $count = count($value);

            if ($count < 2 || ($count & ($count - 1)) !== 0) {
                $fail("Entrant slots must total a power of two (2, 4, 8, 16, …) to form a bracket; got {$count}.");

                return;
            }

            foreach (array_values($value) as $index => $slot) {
                try {
                    EntrantSlot::fromArray(is_array($slot) ? $slot : []);
                } catch (DomainException $e) {
                    $fail('Slot '.($index + 1).': '.$e->getMessage());

                    return;
                }
            }
        };
    }

    /**
     * Canonicalize an incoming entrants list (string ints → ints, dropped
     * extraneous keys) while leaving invalid slots untouched for the
     * validator to reject with a useful message.
     *
     * @param  array<int, mixed>  $entrants
     * @return array<int, mixed>
     */
    public static function normalizeEntrants(array $entrants): array
    {
        return array_map(function (mixed $slot): mixed {
            if (! is_array($slot)) {
                return $slot;
            }

            try {
                return EntrantSlot::fromArray($slot)->toArray();
            } catch (DomainException) {
                return $slot;
            }
        }, array_values($entrants));
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'order' => $this->input('order', 0),
        ]);

        // Coerce config ints that arrive as strings from form payloads, and
        // only keep each key when the format supports it (silently drop it
        // otherwise so it doesn't pollute the JSON column).
        $config = $this->input('config', []);
        if (! is_array($config)) {
            $config = [];
        }

        $format = $this->input('format');

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
            $config['entrants'] = self::normalizeEntrants($config['entrants']);
        } else {
            unset($config['entrants']);
        }

        $this->merge([
            'config' => empty($config) ? null : $config,
        ]);
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
