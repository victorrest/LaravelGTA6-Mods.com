<?php

namespace App\Http\Requests;

use App\Support\EditorJs;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

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
            'body' => ['required', 'json'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $body = $this->input('body');

            if (! $body) {
                return;
            }

            $decoded = EditorJs::decode($body);

            if (! isset($decoded['blocks']) || empty($decoded['blocks'])) {
                $validator->errors()->add('body', 'Thread body must include at least one content block.');

                return;
            }

            $plain = EditorJs::toPlainText($body);

            if (Str::of($plain)->length() < 20) {
                $validator->errors()->add('body', 'Thread body must be at least 20 characters long.');
            }
        });
    }
}
