<?php

namespace App\Http\Requests;

use App\Support\EditorJs;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

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
            'authors' => ['required', 'array', 'min:1'],
            'authors.*' => ['string', 'max:150'],
            'download_url' => ['nullable', 'url', 'required_without_all:mod_file,mod_file_token'],
            'description' => ['required', 'json'],
            'hero_image' => ['nullable', 'image', 'max:4096'],
            'hero_image_token' => ['nullable', 'uuid'],
            'gallery_images' => ['nullable', 'array', 'max:12'],
            'gallery_images.*' => ['image', 'max:8192'],
            'gallery_image_tokens' => ['nullable', 'array'],
            'gallery_image_tokens.*' => ['uuid'],
            'mod_file' => ['nullable', 'file', 'max:204800', 'required_without_all:download_url,mod_file_token'],
            'mod_file_token' => ['nullable', 'uuid', 'required_without_all:download_url,mod_file'],
            'file_size' => ['nullable', 'numeric', 'min:0'],
            'tag_list' => ['nullable', 'array', 'max:20'],
            'tag_list.*' => ['string', 'max:50'],
            'video_permission' => ['required', Rule::in(['deny', 'self_moderate', 'allow'])],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('authors')) {
            $authors = collect($this->input('authors', []))
                ->map(fn ($value) => trim((string) $value))
                ->filter()
                ->unique()
                ->values()
                ->all();

            $this->merge(['authors' => $authors]);
        }

        $tagInput = $this->input('tags');
        if (is_string($tagInput)) {
            $tags = collect(explode(',', $tagInput))
                ->map(fn ($value) => trim($value))
                ->filter()
                ->unique()
                ->values()
                ->all();

            $this->merge(['tag_list' => $tags]);
        } elseif (is_array($tagInput)) {
            $tags = collect($tagInput)
                ->map(fn ($value) => trim((string) $value))
                ->filter()
                ->unique()
                ->values()
                ->all();

            $this->merge(['tag_list' => $tags]);
        }

        if ($this->filled('description_plain') && ! $this->filled('description')) {
            $this->merge([
                'description' => $this->convertPlainTextToEditorJs($this->input('description_plain')),
            ]);
        }

        if ($this->has('gallery_image_tokens') && ! is_array($this->input('gallery_image_tokens'))) {
            $this->merge(['gallery_image_tokens' => []]);
        }
    }

    private function convertPlainTextToEditorJs(?string $plainText): string
    {
        $plainText = (string) $plainText;
        $plainText = Str::of($plainText)->replace("\r", '')->trim();

        if ($plainText->isEmpty()) {
            return json_encode([
                'time' => now()->getTimestampMs(),
                'blocks' => [],
                'version' => '2.28.2',
            ]);
        }

        $paragraphs = collect(preg_split('/\n{2,}/u', $plainText->toString()) ?: [])
            ->map(function (string $paragraph) {
                $paragraph = trim($paragraph);
                if ($paragraph === '') {
                    return null;
                }

                $allowed = '<b><i><u><ul><ol><li><br>';
                $paragraph = strip_tags($paragraph, $allowed);
                $paragraph = preg_replace('/\n/u', '<br>', $paragraph);

                return [
                    'type' => 'paragraph',
                    'data' => [
                        'text' => $paragraph,
                    ],
                ];
            })
            ->filter()
            ->values()
            ->all();

        return json_encode([
            'time' => now()->getTimestampMs(),
            'blocks' => $paragraphs,
            'version' => '2.28.2',
        ], JSON_UNESCAPED_UNICODE);
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

            $tokenCount = count($this->input('gallery_image_tokens', []));
            $fileCount = count($this->file('gallery_images', []));

            if ($tokenCount + $fileCount > 12) {
                $validator->errors()->add('gallery_images', 'You can upload up to 12 screenshots in total.');
            }
        });
    }
}
