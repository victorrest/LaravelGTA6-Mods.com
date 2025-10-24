<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ModUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $mod = $this->route('mod');

        return $mod !== null && $this->user() !== null && $this->user()->is($mod->author);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:150'],
            'version' => ['required', 'string', 'max:50'],
            'category_ids' => ['required', 'array', 'min:1'],
            'category_ids.*' => ['integer', 'exists:mod_categories,id'],
            'download_url' => ['required', 'url'],
            'description' => ['required', 'string', 'min:20'],
            'hero_image' => ['nullable', 'image', 'max:4096'],
            'file_size' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
