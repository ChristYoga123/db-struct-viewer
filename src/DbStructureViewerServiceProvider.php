<?php

namespace Christyoga123\DbStructureViewer;

use Illuminate\Support\ServiceProvider;
use Christyoga123\DbStructureViewer\Console\Commands\ShowDbStructureCommand;

class DbStructureViewerServiceProvider extends ServiceProvider
{
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                ShowDbStructureCommand::class,
            ]);
        }
    }

    public function register()
    {
        //
    }
}