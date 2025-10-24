<?php

namespace App\Http\Requests;

use App\Support\EditorJs;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

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
            'download_url' => ['nullable', 'url', 'required_without:mod_file'],
            'description' => ['required', 'json'],
            'hero_image' => ['nullable', 'image', 'max:4096'],
            'gallery_images' => ['nullable', 'array', 'max:12'],
            'gallery_images.*' => ['image', 'max:8192'],
            'remove_gallery_image_ids' => ['nullable', 'array'],
            'remove_gallery_image_ids.*' => ['integer', 'exists:mod_gallery_images,id'],
            'mod_file' => ['nullable', 'file', 'max:204800', 'required_without:download_url'],
            'file_size' => ['nullable', 'numeric', 'min:0'],
            'changelog' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $description = $this->input('description');

            if ($description) {
                $decoded = EditorJs::decode($description);

                if (! isset($decoded['blocks']) || empty($decoded['blocks'])) {
                    $validator->errors()->add('description', 'Please add some content to the description.');
                } else {
                    $plain = EditorJs::toPlainText($description);

                    if (Str::of($plain)->length() < 20) {
                        $validator->errors()->add('description', 'Description must be at least 20 characters of meaningful text.');
                    }
                }
            }

            $fileCount = count($this->file('gallery_images', []));

            if ($fileCount > 12) {
                $validator->errors()->add('gallery_images', 'You can upload up to 12 screenshots in total.');
            }
        });
    }
}
