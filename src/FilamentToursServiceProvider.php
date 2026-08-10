<?php

namespace Rolland\FilamentTours;

use Filament\Support\Assets\AlpineComponent;
use Filament\Support\Assets\Asset;
use Filament\Support\Assets\Css;
use Filament\Support\Facades\FilamentAsset;
use Filament\Support\Facades\FilamentIcon;
use Illuminate\Filesystem\Filesystem;
use Livewire\Features\SupportTesting\Testable;
use Rolland\FilamentTours\Commands\ListToursCommand;
use Rolland\FilamentTours\Contracts\TourState;
use Rolland\FilamentTours\State\LocalStorageState;
use Rolland\FilamentTours\Testing\TestsFilamentTours;
use Spatie\LaravelPackageTools\Commands\InstallCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class FilamentToursServiceProvider extends PackageServiceProvider
{
    public static string $name = 'filament-tours';

    public static string $viewNamespace = 'filament-tours';

    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package->name(static::$name)
            ->hasCommands($this->getCommands())
            ->hasInstallCommand(function (InstallCommand $command) {
                $command
                    ->publishConfigFile()
                    ->askToStarRepoOnGitHub('rolland97/filament-tours');
            });

        // ⚠️ shortName() is Str::after($name, 'laravel-'), and 'filament-tours'
        // has no such prefix, so it returns unchanged. The config file must
        // therefore be config/filament-tours.php — the skeleton's config/tours.php
        // never matched this guard, so the config was silently never loaded.
        $configFileName = $package->shortName();

        if (file_exists($package->basePath("/../config/{$configFileName}.php"))) {
            $package->hasConfigFile();
        }

        // No hasMigrations() and no hasTranslations(): the package ships neither.
        // Persistence and wording are the host's, not ours.

        if (file_exists($package->basePath('/../resources/views'))) {
            $package->hasViews(static::$viewNamespace);
        }
    }

    public function packageRegistered(): void
    {
        // Minimal binding: the browser-local default. Resolving a host
        // class-string from config('filament-tours.state') lands in T064.
        $this->app->bind(TourState::class, LocalStorageState::class);
    }

    public function packageBooted(): void
    {
        // Asset Registration
        FilamentAsset::register(
            $this->getAssets(),
            $this->getAssetPackageName()
        );

        FilamentAsset::registerScriptData(
            $this->getScriptData(),
            $this->getAssetPackageName()
        );

        // Icon Registration
        FilamentIcon::register($this->getIcons());

        // Handle Stubs
        if (app()->runningInConsole()) {
            foreach (app(Filesystem::class)->files(__DIR__ . '/../stubs/') as $file) {
                $this->publishes([
                    $file->getRealPath() => base_path("stubs/filament-tours/{$file->getFilename()}"),
                ], 'filament-tours-stubs');
            }
        }

        // Testing
        Testable::mixin(new TestsFilamentTours);
    }

    protected function getAssetPackageName(): ?string
    {
        return 'rolland97/filament-tours';
    }

    /**
     * @return array<Asset>
     */
    protected function getAssets(): array
    {
        return [
            AlpineComponent::make('filament-tours', __DIR__ . '/../resources/dist/components/filament-tours.js'),
            // esbuild emits the stylesheet next to the entry point's outfile, so it
            // lands in components/ too. Registered from where the build puts it,
            // not from where a plan guessed it would.
            Css::make('filament-tours', __DIR__ . '/../resources/dist/components/filament-tours.css'),
        ];
    }

    /**
     * @return array<class-string>
     */
    protected function getCommands(): array
    {
        return [
            ListToursCommand::class,
        ];
    }

    /**
     * @return array<string>
     */
    protected function getIcons(): array
    {
        return [];
    }

    /**
     * @return array<string>
     */
    protected function getRoutes(): array
    {
        return [];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getScriptData(): array
    {
        return [];
    }
}
