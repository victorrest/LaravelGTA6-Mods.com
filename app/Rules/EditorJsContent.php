<?php

namespace App\Rules;

use App\Support\EditorJsRenderer;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class EditorJsContent implements ValidationRule
{
    public function __construct(private int $minimumCharacters = 0)
    {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || trim($value) === '') {
            $fail(__('validation.required', ['attribute' => str_replace('_', ' ', $attribute)]));

            return;
        }

        $decoded = EditorJsRenderer::decode($value);

        if ($decoded === null) {
            $fail(__('validation.json', ['attribute' => str_replace('_', ' ', $attribute)]));

            return;
        }

        if ($this->minimumCharacters <= 0) {
            return;
        }

        $plainText = EditorJsRenderer::toPlainText($value, $decoded);

        if (mb_strlen($plainText) < $this->minimumCharacters) {
            $fail(__('validation.min.string', [
                'attribute' => str_replace('_', ' ', $attribute),
                'min' => $this->minimumCharacters,
            ]));
        }
    }
}
