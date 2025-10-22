<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ModStoreRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:150'],
            'version' => ['required', 'string', 'max:50'],
            'category_ids' => ['required', 'array', 'min:1'],
            'category_ids.*' => ['integer', 'exists:mod_categories,id'],
            'download_url' => ['nullable', 'url', 'required_without:mod_file'],
            'description' => ['required', 'string', 'min:20'],
            'hero_image' => ['nullable', 'image', 'max:4096'],
            'gallery_images' => ['nullable', 'array', 'max:12'],
            'gallery_images.*' => ['image', 'max:8192'],
            'mod_file' => ['nullable', 'file', 'max:204800', 'required_without:download_url'],
            'file_size' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
