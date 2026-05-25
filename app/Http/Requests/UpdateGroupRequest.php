<?php

namespace App\Http\Requests;

use App\Models\Group;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        $group = $this->route('group');
        $stage = $this->route('stage');

        return $group instanceof Group
            && ($this->user()?->can('update', $stage) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $group = $this->route('group');
        $stageId = $group instanceof Group ? $group->stage_id : null;
        $groupId = $group instanceof Group ? $group->id : null;

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('groups', 'name')
                    ->ignore($groupId)
                    ->where(fn ($q) => $q->where('stage_id', $stageId)),
            ],
            'order' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.unique' => 'A group with that name already exists in this stage.',
        ];
    }
}
