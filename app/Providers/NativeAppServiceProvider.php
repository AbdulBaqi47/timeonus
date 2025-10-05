<?php

namespace App\Providers;

use Native\Laravel\Contracts\ProvidesPhpIni;
use Native\Laravel\Facades\NativeApplication;
use Native\Laravel\Facades\Window;

class NativeAppServiceProvider implements ProvidesPhpIni
{
    public function boot(): void
    {
        NativeApplication::setAutoStart(true);

        Window::open()
            ->route('native.dashboard')
            ->title('TimeOnUs Tracker')
            ->width(1280)
            ->height(800)
            ->resizable(true)
            ->show();
    }

    public function phpIni(): array
    {
        return [];
    }
}