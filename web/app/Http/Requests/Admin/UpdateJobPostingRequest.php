<?php

namespace App\Http\Requests\Admin;

use App\Services\JobClassifier;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Correção manual de uma vaga pelo painel de administração. */
class UpdateJobPostingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('admin');
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:250'],
            'company' => ['nullable', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'url' => ['required', 'url', 'max:1000'],
            'seniority' => ['nullable', Rule::in(JobClassifier::SENIORITIES)],
            'area' => ['nullable', Rule::in(array_keys(JobClassifier::AREAS))],
            'work_mode' => ['nullable', Rule::in(array_keys(JobClassifier::WORK_MODES))],
            'techs' => ['nullable', 'string', 'max:1000'],
            'description' => ['nullable', 'string', 'max:60000'],
        ];
    }
}
