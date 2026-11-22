<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class PackageServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $loader = require base_path('vendor/autoload.php');
        $manifestPath = base_path('bootstrap/cache/packages_discovered.php');

        $manifest = null;
        if (file_exists($manifestPath)) {
            $manifest = require $manifestPath;
        }

        if (!is_array($manifest)) {
            $manifest = $this->buildManifest($manifestPath);
        }

        if (isset($manifest['psr4'])) {
            foreach ($manifest['psr4'] as $namespace => $path) {
                $loader->addPsr4($namespace, $path);
            }
        }

        if (isset($manifest['providers'])) {
            foreach ($manifest['providers'] as $provider) {
                $this->app->register($provider);
            }
        }
    }

    protected function buildManifest(string $manifestPath): array
    {
        $manifest = [
            'psr4' => [],
            'providers' => [],
        ];

        $packageDirectories = glob(base_path('packages/workdo/*'), GLOB_ONLYDIR);

        if ($packageDirectories) {
            foreach ($packageDirectories as $packageDir) {
                $composerFile = $packageDir . '/composer.json';

                if (file_exists($composerFile)) {
                    $composerConfig = json_decode(file_get_contents($composerFile), true);

                    if (isset($composerConfig['autoload']['psr-4'])) {
                        foreach ($composerConfig['autoload']['psr-4'] as $namespace => $path) {
                            $manifest['psr4'][$namespace] = $packageDir . '/' . $path;
                        }
                    }

                    if (isset($composerConfig['extra']['laravel']['providers'])) {
                        foreach ($composerConfig['extra']['laravel']['providers'] as $provider) {
                            $manifest['providers'][] = $provider;
                        }
                    }
                }
            }
        }

        @file_put_contents(
            $manifestPath,
            '<?php return ' . var_export($manifest, true) . ';' . PHP_EOL
        );

        return $manifest;
    }

    public function boot(): void
    {
        //
    }
}