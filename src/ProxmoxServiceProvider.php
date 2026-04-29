<?php

namespace Nawasara\Proxmox;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Nawasara\Proxmox\Services\ProxmoxClient;
use Symfony\Component\Finder\Finder;

class ProxmoxServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'nawasara-proxmox');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        Blade::anonymousComponentPath(__DIR__.'/../resources/views/components', 'nawasara-proxmox');
        $this->registerLivewire();
    }

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/nawasara-proxmox.php', 'nawasara-proxmox');
        $this->app->singleton(ProxmoxClient::class, fn () => new ProxmoxClient());
    }

    protected function registerLivewire(): void
    {
        $namespace = 'Nawasara\\Proxmox\\Livewire';
        $basePath = __DIR__.'/Livewire';

        if (! is_dir($basePath)) {
            return;
        }

        $finder = new Finder();
        $finder->files()->in($basePath)->name('*.php');

        foreach ($finder as $file) {
            $relativePath = str_replace('/', '\\', $file->getRelativePathname());
            $class = $namespace.'\\'.Str::beforeLast($relativePath, '.php');

            if (class_exists($class)) {
                $alias = 'nawasara-proxmox.'.
                    Str::of($relativePath)
                        ->replace('.php', '')
                        ->replace('\\', '.')
                        ->replace('/', '.')
                        ->explode('.')
                        ->map(fn ($segment) => Str::kebab($segment))
                        ->join('.');

                Livewire::component($alias, $class);
            }
        }
    }
}
