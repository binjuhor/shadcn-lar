<?php

namespace Modules\ModuleGenerator\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\ModuleGenerator\Console\Commands\ModuleScaffoldCommand;

class ModuleGeneratorServiceProvider extends ServiceProvider
{
    protected string $name = 'ModuleGenerator';

    protected string $nameLower = 'module-generator';

    public function boot(): void
    {
        $this->registerCommands();
        $this->registerConfig();
    }

    public function register(): void
    {
        //
    }

    protected function registerCommands(): void
    {
        $this->commands([
            ModuleScaffoldCommand::class,
        ]);
    }

    protected function registerConfig(): void
    {
        $configPath = module_path($this->name, 'config/config.php');

        $this->publishes([
            $configPath => config_path($this->nameLower.'.php'),
        ], 'config');

        $this->mergeConfigFrom($configPath, $this->nameLower);
    }
}
