<?php

namespace App\Http\Controllers;

use App\Models\Config;
use App\Models\Store;
use App\Services\BigCommerceService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ConfigController extends Controller
{
    public function handleConfiguration(Request $request, BigCommerceService $bigCommerceService)
    {
        $this->validate($request, [
            'store_hash' => 'required',
            'access_token' => 'required',
            'context' => 'required',
        ]);

        $storeHash = $request->input('store_hash');
        $accessToken = $request->input('access_token');
        $context = $request->input('context');
        $shopkey = $request->input('shopkey');

        $activeStatus = ($request->input('active_status') == '') ? null : true;
        $store = Store::whereContext($context)->first();

        if (isset($store['id'])) {
            $storeId = $store['id'];
        } else {
            return new Response('Store is not available', 400);
        }

        $configRow = Config::whereSotreId($storeId)->first();
        if (isset($configRow['id'])) {
            $config = Config::find($configRow['id']);
        }
        $config = isset($config) ? $config : new Config();
        $config->active = isset($activeStatus) ? true : false;
        $config->store_id = $storeId;
        $config->shopkey = $shopkey;
        $config->save();

        $this->storeToSession([
            'access_token' => $accessToken,
            'context' => $context,
            'store_hash' => $storeHash,
            'saved' => true
        ]);

        $settings = $bigCommerceService->getStoreSettings();
        if (isset($settings['features']['stencil_enabled'])) {
            $bigCommerceService->deleteExistingScript();
            if ($config->active) {
                $bigCommerceService->createScript($config);
            }
        }

        return view('app', [
            'shopkey' => $shopkey,
            'active_status' => $activeStatus
        ]);
    }
}
