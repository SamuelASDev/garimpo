<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Descrição completa de uma vaga colada pelo usuário. */
class StorePastedDescriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return ['description' => ['required', 'string', 'min:200', 'max:30000']];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['description.min' => 'Cole a descrição completa da vaga (pelo menos 200 caracteres).'];
    }
}
