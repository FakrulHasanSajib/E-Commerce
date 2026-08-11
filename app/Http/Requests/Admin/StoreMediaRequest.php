<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreMediaRequest extends FormRequest
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'file' => 'nullable|file|max:51200', // max 50MB
            'files' => 'nullable|array',
            'files.*' => 'file|max:51200',
            'folder_id' => 'nullable|integer|exists:media_folders,id',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
        ];
    }
}
