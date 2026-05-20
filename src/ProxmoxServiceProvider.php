<?php

namespace Nawasara\Proxmox;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Nawasara\Proxmox\Console\Commands\SyncCommand;
use Nawasara\Proxmox\Jobs\SyncProxmoxNodesJob;
use Nawasara\Proxmox\Jobs\SyncProxmoxVmsJob;
use Nawasara\Proxmox\Services\ProxmoxClient;
use Symfony\Component\Finder\Finder;

class ProxmoxServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Commands didaftarkan duluan — sebelum operasi lain yang mungkin gagal.
        if ($this->app->runningInConsole()) {
            $this->commands([
                SyncCommand::class,
            ]);
        }

        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'nawasara-proxmox');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        // Guarded — Laravel's view:cache crashes on missing registered paths.
        if (is_dir(__DIR__.'/../resources/views/components')) {
            Blade::anonymousComponentPath(__DIR__.'/../resources/views/components', 'nawasara-proxmox');
        }
        $this->registerLivewire();

        $this->app->booted(function () {
            if (! $this->app->runningInConsole()) {
                return;
            }

            // Skip kalau scheduler dimatikan — mis. deployment tanpa
            // kredensial Proxmox, di mana task ini cuma akan gagal tiap run.
            if (! config('nawasara-proxmox.scheduler.enabled', true)) {
                return;
            }

            $schedule = $this->app->make(Schedule::class);

            // Sync nodes + VMs tiap `sync_interval` menit (default 15).
            // VM/node list relatif stabil — interval ini cukup untuk
            // dashboard yang fresh tanpa membanjiri Proxmox API.
            //
            // Dispatch job langsung lewat $schedule->call() — TIDAK lewat
            // $schedule->command('proxmox:sync'). Console command yang
            // didaftarkan via $this->commands() tidak selalu surface di
            // Artisan kernel (paket yang boot belakangan), jadi
            // $schedule->command() bisa gagal "namespace not defined".
            // $schedule->call() jalan di proses scheduler sendiri — tidak
            // butuh command terdaftar.
            $interval = max(1, (int) config('nawasara-proxmox.sync_interval', 15));

            $schedule->call(function () {
                SyncProxmoxNodesJob::dispatch(triggerSource: 'scheduled');
                SyncProxmoxVmsJob::dispatch(triggerSource: 'scheduled');
            })
                ->name('nawasara-proxmox:sync')
                ->cron("*/{$interval} * * * *")
                ->withoutOverlapping(10);
        });
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
