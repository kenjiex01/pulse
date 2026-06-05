<?php

namespace App\Providers;

use Native\Laravel\Contracts\ProvidesPhpIni;
use Native\Laravel\Facades\Window;

class NativeAppServiceProvider implements ProvidesPhpIni
{
    public function boot(): void
    {
        Window::open()
            ->title('PULSO')
            ->width(1280)
            ->height(800);
    }

    public function phpIni(): array
    {
        return [];
    }
}
