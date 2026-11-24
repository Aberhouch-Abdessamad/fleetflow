<?php

namespace App\Http\Requests\Api\V1;

use App\Support\Uploads;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DocumentRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'document_type' => ['required', Rule::in(['insurance', 'registration', 'inspection', 'permit', 'license', 'medical', 'contract', 'id_card', 'other'])],
            'document_number' => ['nullable', 'string', 'max:64'],
            'issue_date' => ['nullable', 'date'],
            'expiry_date' => ['nullable', 'date', 'after_or_equal:issue_date'],
            'file' => ['nullable', ...Uploads::DOCUMENT_RULES],
        ];
    }
}
