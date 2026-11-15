<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Alerta criado a partir dos filtros da lista de vagas (os valores são limpos em JobAlert::cleanFilters). */
class StoreJobAlertRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:80'],
            'q' => ['nullable', 'string', 'max:60'],
            'seniority' => ['nullable', 'string'],
            'area' => ['nullable', 'string'],
            'tech' => ['nullable', 'array', 'max:10'],
            'tech.*' => ['string'],
            'remote' => ['nullable'],
            'mode' => ['nullable', 'string'],
            'city' => ['nullable', 'string', 'max:60'],
        ];
    }
}
