<?php

namespace App\Providers;

use App\Http\Middleware\PermisoModulo;
use App\Support\Avisos;
use App\Support\NegocioActual;
use Carbon\Carbon;
use Illuminate\Foundation\Console\ServeCommand;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(NegocioActual::class);
        $this->app->scoped(Avisos::class);
    }

    public function boot(): void
    {
        Carbon::setLocale('es');
        // en Windows, sin TEMP/TMP el servidor de `artisan serve` no puede recibir archivos subidos
        ServeCommand::$passthroughVariables = [...ServeCommand::$passthroughVariables, 'TEMP', 'TMP'];
        // las acciones de cada pantalla vuelven a revisar el permiso del módulo
        Livewire::addPersistentMiddleware([PermisoModulo::class]);
    }
}
