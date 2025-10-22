<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ModUpdateRequest extends FormRequest
{
    /**
     * The names of the attributes that should not be flashed to the session on validation errors.
     */
    protected $dontFlash = [
        'hero_image_token',
        'mod_file_token',
    ];

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
            'download_url' => ['nullable', 'url', 'required_without_all:mod_file,mod_file_token'],
            'description' => ['required', 'string', 'min:20'],
            'hero_image' => ['nullable', 'image', 'max:4096'],
            'hero_image_token' => ['nullable', 'uuid'],
            'gallery_images' => ['nullable', 'array', 'max:12'],
            'gallery_images.*' => ['image', 'max:8192'],
            'gallery_image_tokens' => ['nullable', 'array'],
            'gallery_image_tokens.*' => ['uuid'],
            'remove_gallery_image_ids' => ['nullable', 'array'],
            'remove_gallery_image_ids.*' => ['integer', 'exists:mod_gallery_images,id'],
            'mod_file' => ['nullable', 'file', 'max:204800', 'required_without_all:download_url,mod_file_token'],
            'mod_file_token' => ['nullable', 'uuid'],
            'file_size' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('gallery_image_tokens') && ! is_array($this->input('gallery_image_tokens'))) {
            $this->merge(['gallery_image_tokens' => []]);
        }
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $tokenCount = count($this->input('gallery_image_tokens', []));
            $fileCount = count($this->file('gallery_images', []));

            if ($tokenCount + $fileCount > 12) {
                $validator->errors()->add('gallery_images', 'You can upload up to 12 screenshots in total.');
            }
        });
    }
}
