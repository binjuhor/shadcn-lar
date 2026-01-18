<?php

namespace Modules\Settings\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Artisan, Redirect};
use Inertia\{Inertia, Response};
use Nwidart\Modules\Facades\Module;

class ModulesController extends Controller
{
    public function index(): Response
    {
        abort_unless(auth()->user()->hasRole('Super Admin'), 403);

        $modules = collect(Module::all())->map(function ($module) {
            $config = json_decode(
                file_get_contents($module->getPath().'/module.json'),
                true
            );

            return [
                'name' => $module->getStudlyName(),
                'alias' => $config['alias'] ?? strtolower($module->getStudlyName()),
                'description' => $config['description'] ?? '',
                'keywords' => $config['keywords'] ?? [],
                'priority' => $config['priority'] ?? 0,
                'enabled' => $module->isEnabled(),
                'isCore' => $module->getStudlyName() === 'Permission',
            ];
        })->sortBy('priority')->values();

        return Inertia::render('settings/modules/index', [
            'modules' => $modules,
        ]);
    }

    public function toggle(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()->hasRole('Super Admin'), 403);

        $request->validate([
            'name' => 'required|string',
        ]);

        $moduleName = $request->input('name');
        $module = Module::find($moduleName);

        abort_unless($module, 404, 'Module not found');

        if ($moduleName === 'Permission') {
            return Redirect::back()->with('error', 'Permission module cannot be disabled.');
        }

        $wasEnabled = $module->isEnabled();

        $wasEnabled
            ? Module::disable($moduleName)
            : Module::enable($moduleName);

        // Clear module cache if command exists
        try {
            Artisan::call('module:clear');
        } catch (\Exception $e) {
            // Command may not exist in testing or certain configurations
        }

        $action = $wasEnabled ? 'disabled' : 'enabled';

        return Redirect::back()->with('success', "Module {$moduleName} {$action} successfully.");
    }

    public function reorder(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()->hasRole('Super Admin'), 403);

        $request->validate([
            'order' => 'required|array',
            'order.*' => 'required|string',
        ]);

        $request->user()->update([
            'sidebar_settings' => [
                'module_order' => $request->input('order'),
            ],
        ]);

        return Redirect::back()->with('success', 'Module order saved successfully.');
    }
}
