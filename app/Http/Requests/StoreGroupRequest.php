<?php

namespace App\Http\Requests;

use App\Models\Stage;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGroupRequest extends FormRequest
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
        $stageId = $stage instanceof Stage ? $stage->id : null;

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('groups', 'name')->where(fn ($q) => $q->where('stage_id', $stageId)),
            ],
            'order' => ['nullable', 'integer', 'min:0', 'max:65535'],
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
            'name.unique' => 'A group with that name already exists in this stage.',
        ];
    }
}
