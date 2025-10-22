<?php

namespace App\Http\Requests;

use App\Rules\EditorJsContent;
use Illuminate\Foundation\Http\FormRequest;

class ThreadStoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:140'],
            'flair' => ['nullable', 'string', 'max:30'],
            'body' => ['required', 'string', new EditorJsContent(20)],
        ];
    }
}
