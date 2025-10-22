![image](https://user-images.githubusercontent.com/30468274/162574530-f9af87ef-79d4-41de-8ddb-9ebf60563ac9.png)

# Laravel-Editor.js

A simple editor.js html parser for Laravel with Tailwind styling

[![Latest Version on Packagist](https://img.shields.io/packagist/v/lostlink/laravel-editorjs.svg?style=for-the-badge)](https://packagist.org/packages/lostlink/laravel-editorjs)
[![Total Downloads](https://img.shields.io/packagist/dt/lostlink/laravel-editorjs.svg?style=for-the-badge)](https://packagist.org/packages/lostlink/laravel-editorjs)

---

## Installation

You can install the package via composer:

```bash
composer require lostlink/laravel-editorjs
```

You can publish the config file with:

```bash
php artisan vendor:publish --tag="laravel_editorjs-config"
```

Optionally, you can publish the views using

```bash
php artisan vendor:publish --tag="laravel_editorjs-views"
```

## Usage

```php
use App\Models\Post;

$post = Post::find(1);
echo LaravelEditorJs::render($post->body);
```

Defining An Accessor

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Lostlink\LaravelEditorJs\Facades\LaravelEditorJs;

class Post extends Model
{
    public function getBodyAttribute()
    {
        return LaravelEditorJs::render($this->attributes['body']);
    }
}

$post = Post::find(1);
echo $post->body;
```

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Credits

-   [Al-Amin Firdows](https://github.com/alaminfirdows)
-   [Nuno Souto](https://github.com/nsouto)
-   [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
