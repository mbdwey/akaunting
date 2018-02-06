<?php

namespace App\Http\Controllers\Modules;

use App\Http\Controllers\Controller;
use App\Models\Module\Module;
use App\Models\Module\ModuleHistory;
use App\Traits\Modules;
use Artisan;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;

class Item extends Controller
{
    use Modules;

    /**
     * Instantiate a new controller instance.
     *
     * @param  Route  $route
     */
    public function __construct(Route $route)
    {
        parent::__construct($route);

        // Add CRUD permission check
        $this->middleware('permission:create-modules-item')->only(['install']);
        $this->middleware('permission:update-modules-item')->only(['update', 'enable', 'disable']);
        $this->middleware('permission:delete-modules-item')->only(['uninstall']);
    }

    /**
     * Show the form for viewing the specified resource.
     *
     * @param  $alias
     *
     * @return Response
     */
    public function show($alias)
    {
        $this->checkApiToken();

        $enable = false;
        $installed = false;

        $module = $this->getModule($alias);
        if (!$module) {
            return $this->itemNotfound();
        }
        $check = Module::alias($alias)->first();

        if ($check) {
            $installed = true;

            if ($check->status) {
                $enable = true;
            }
        }

        return view('modules.item.show', compact('module', 'about', 'installed', 'enable'));
    }

    /**
     * Show the form for viewing the specified resource.
     *
     * @param  $request
     *
     * @return Response
     */
    public function steps(Request $request)
    {
        $this->checkApiToken();

        $json = [];
        $json['step'] = [];

        $name = $request['name'];
        $version = $request['version'];

        // Download
        $json['step'][] = [
            'text' => trans('modules.installation.download', ['module' => $name]),
            'url'  => url('apps/download')
        ];

        // Unzip
        $json['step'][] = [
            'text' => trans('modules.installation.unzip', ['module' => $name]),
            'url'  => url('apps/unzip')
        ];

        // Download
        $json['step'][] = [
            'text' => trans('modules.installation.install', ['module' => $name]),
            'url'  => url('apps/install')
        ];

        return response()->json($json);
    }

    /**
     * Show the form for viewing the specified resource.
     *
     * @param  $request
     *
     * @return Response
     */
    public function download(Request $request)
    {
        $this->checkApiToken();

        $path = $request['path'];

        $version = $request['version'];

        $path .= '/' . $version . '/' . version('short') . '/' . setting('general.api_token');

        $json = $this->downloadModule($path);

        return response()->json($json);
    }

    /**
     * Show the form for viewing the specified resource.
     *
     * @param  $request
     *
     * @return Response
     */
    public function unzip(Request $request)
    {
        $this->checkApiToken();

        $path = $request['path'];

        $json = $this->unzipModule($path);

        return response()->json($json);
    }

    /**
     * Show the form for viewing the specified resource.
     *
     * @param  $request
     *
     * @return Response
     */
    public function install(Request $request)
    {
        $this->checkApiToken();

        $path = $request['path'];

        $json = $this->installModule($path);

        if ($json['success']) {
            Artisan::call('module:install', ['alias' => $json['data']['alias'], 'company_id' => session('company_id')]);

            $message = trans('modules.installed', ['module' => $json['data']['name']]);

            flash($message)->success();
        }

        return response()->json($json);
    }

    public function uninstall($alias)
    {
        $this->checkApiToken();

        $json = $this->uninstallModule($alias);

        $module = Module::alias($alias)->first();

        $data = [
            'company_id' => session('company_id'),
            'module_id' => $module->id,
            'category' => $json['data']['category'],
            'version' => $json['data']['version'],
            'description' => trans('modules.uninstalled', ['module' => $json['data']['name']]),
        ];

        ModuleHistory::create($data);

        $module->delete();

        $message = trans('modules.uninstalled', ['module' => $json['data']['name']]);

        flash($message)->success();

        return redirect('apps/' . $alias)->send();
    }

    public function update($alias)
    {
        $this->checkApiToken();

        $json = $this->updateModule($alias);

        $module = Module::alias($alias)->first();

        $data = [
            'company_id' => session('company_id'),
            'module_id' => $module->id,
            'category' => $json['data']['category'],
            'version' => $json['data']['version'],
            'description' => trans_choice('modules.updated', $json['data']['name']),
        ];

        ModuleHistory::create($data);

        $message = trans('modules.updated', ['module' => $json['data']['name']]);

        flash($message)->success();

        return redirect('apps/' . $alias)->send();
    }

    public function enable($alias)
    {
        $this->checkApiToken();

        $json = $this->enableModule($alias);

        $module = Module::alias($alias)->first();

        $data = [
            'company_id' => session('company_id'),
            'module_id' => $module->id,
            'category' => $json['data']['category'],
            'version' => $json['data']['version'],
            'description' => trans('modules.enabled', ['module' => $json['data']['name']]),
        ];

        $module->status = 1;

        $module->save();

        ModuleHistory::create($data);

        $message = trans('modules.enabled', ['module' => $json['data']['name']]);

        flash($message)->success();

        return redirect('apps/' . $alias)->send();
    }

    public function disable($alias)
    {
        $this->checkApiToken();

        $json = $this->disableModule($alias);

        $module = Module::alias($alias)->first();

        $data = [
            'company_id' => session('company_id'),
            'module_id' => $module->id,
            'category' => $json['data']['category'],
            'version' => $json['data']['version'],
            'description' => trans('modules.disabled', ['module' => $json['data']['name']]),
        ];

        $module->status = 0;

        $module->save();

        ModuleHistory::create($data);

        $message = trans('modules.disabled', ['module' => $json['data']['name']]);

        flash($message)->success();

        return redirect('apps/' . $alias)->send();
    }
}
