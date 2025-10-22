<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('gta6:link-assets {--force : Recreate the published assets even when the destination exists}', function (): int {
    $sourceRoot = config('gta6.asset_source');

    if (! $sourceRoot || ! File::isDirectory($sourceRoot)) {
        $this->error('The configured GTA6_ASSET_SOURCE directory is missing.');

        return self::FAILURE;
    }

    $mappings = [
        public_path('assets/icons') => $sourceRoot . DIRECTORY_SEPARATOR . 'assets/icons',
        public_path('img/avatars') => $sourceRoot . DIRECTORY_SEPARATOR . 'img/avatars',
        public_path('img/bg') => $sourceRoot . DIRECTORY_SEPARATOR . 'img/bg',
    ];

    $force = (bool) $this->option('force');
    $copied = 0;

    foreach ($mappings as $destination => $source) {
        if (! File::exists($source)) {
            $this->warn("Skipping missing asset source: {$source}");

            continue;
        }

        if (File::exists($destination)) {
            if (! $force) {
                $this->line("Assets already published at {$destination}. Use --force to recreate them.");

                continue;
            }

            if (File::isDirectory($destination)) {
                File::deleteDirectory($destination);
            } else {
                File::delete($destination);
            }
        }

        File::ensureDirectoryExists(dirname($destination));
        File::copyDirectory($source, $destination);
        $this->info("Copied assets from {$source} to {$destination}.");
        $copied++;
    }

    $faviconSource = $sourceRoot . DIRECTORY_SEPARATOR . 'assets/icons/favicon-32x32.png';
    $faviconDestination = public_path('favicon.ico');

    if (File::exists($faviconSource)) {
        if (File::exists($faviconDestination) && $force) {
            File::delete($faviconDestination);
        }

        if (! File::exists($faviconDestination)) {
            File::copy($faviconSource, $faviconDestination);
            $this->info("Copied favicon to {$faviconDestination}.");
            $copied++;
        }
    } else {
        $this->warn('Favicon source asset is missing from the configured asset directory.');
    }

    if ($copied === 0) {
        $this->line('No assets were copied. Use --force to overwrite existing files.');
    }

    return self::SUCCESS;
})->purpose('Copy the original theme graphics and icons into the Laravel public directory.');
