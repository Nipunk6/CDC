<?php

namespace App\Http\Requests;

use App\Models\Jnf;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class StoreJnfRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // SEC-022: a company editing another company's JNF gets the same 404 as for a missing one, before any
        // validation runs (validation first answered 422 and so revealed which ids exist). Admins edit any company's.
        $form = $this->route('jnf');
        $user = $this->user();
        if ($form instanceof Jnf && $user?->role === 'company' && (int) $form->company_id !== (int) $user->company_id) {
            throw new HttpResponseException(response()->json(['message' => 'JNF not found.'], 404));
        }

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
            'job_title' => ['required', 'string', 'max:255'],
            'job_description' => ['required', 'string', 'max:5000'],
            'job_location' => ['nullable', 'string', 'max:255'],
            'ctc_min' => ['nullable', 'integer', 'min:0'],
            'ctc_max' => ['nullable', 'integer', 'min:0', 'gte:ctc_min'],
            'vacancies' => ['nullable', 'integer', 'min:1'],
            'application_deadline' => ['nullable', 'date'],
            'form_data' => ['nullable', 'json'],
            'status' => ['nullable', Rule::in(['draft', 'submitted'])],
        ];
    }
}
