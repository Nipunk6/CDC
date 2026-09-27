<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInfRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'internship_title' => ['required', 'string', 'max:255'],
            'internship_description' => ['required', 'string', 'max:5000'],
            'internship_location' => ['nullable', 'string', 'max:255'],
            'stipend' => ['nullable', 'integer', 'min:0'],
            'internship_duration_weeks' => ['nullable', 'integer', 'min:1'],
            'vacancies' => ['nullable', 'integer', 'min:1'],
            'application_deadline' => ['nullable', 'date'],
            'form_data' => ['nullable', 'json'],
            'status' => ['nullable', Rule::in(['draft', 'submitted'])],
        ];
    }
}
