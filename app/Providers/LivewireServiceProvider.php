<?php

namespace App\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class LivewireServiceProvider extends ServiceProvider
{
    /**
     * Relying on livewire's default assets loading (route-based) was not reliable on shared hosting/sub-directories usage
     * In here, we custom override the assets fetching and /update routes, taking that in consideration.
     * @return false|void
     */
    public function boot()
    {

        if (!InstallerServiceProvider::checkIfInstalled()) {
            return false;
        }
        $basePath = $this->getBasePath();

        Livewire::setScriptRoute(function ($handle) use ($basePath) {
            return Route::get($basePath.'/libs/livewire/livewire.js', $handle)->middleware('web')
                ->name('livewire.script.test'); // << Renaming the route is a L12 required workaround
        });

        Livewire::setUpdateRoute(function ($handle) use ($basePath) {
            return Route::post($basePath.'/livewire/update', $handle)->middleware('web')
                ->name('livewire.update.test'); // << Renaming the route is a L12 required workaround
        });

    }

    private function getBasePath(): string
    {
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        // if root, this will be empty string, else subdirectory path like "/public"
        return rtrim(str_replace('/index.php', '', $scriptName), '/');
    }
}
