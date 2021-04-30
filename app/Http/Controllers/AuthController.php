<?php

namespace App\Http\Controllers;

use App\Models\Config;
use App\Models\Store;
use App\Models\User;
use App\Services\BigCommerceService;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AuthController extends Controller
{
    /**
     * App load url as defined in app configuration
     */
    public function load(Request $request, BigCommerceService $bigCommerceService)
    {
        $this->validate($request, ['signed_payload' => 'required']);
        $signedPayload = $request->input('signed_payload');

        $data = $bigCommerceService->verifySignedRequest($signedPayload);
        $store = Store::whereContext($data['context'])->firstOrFail();

        User::whereBigcommerceUserId($data['user']['id'])->firstOrCreate([
            'email' => $data['user']['email'],
            'role' => 'user',
            'bigcommerce_user_id' => $data['user']['id'],
            'store_id' => $store->id
        ]);

        $this->storeToSession([
            'access_token' => $store['access_token'],
            'context' => $data['context'],
            'store_hash' => $data['store_hash']
        ]);

        $config = Config::whereStoreId($store['id'])->first();
        $viewData = [];
        if ($config) {
            $activeStatus = null;
            if ($config->active > 0) {
                $activeStatus = $config->active;
            }

            $viewData = [
                'shopkey' => $config->shopkey,
                'active_status' => $activeStatus
            ];
        }

        return view('config', $viewData);
    }

    /**
     *  App install callback url as defined in app configuration
     */
    public function install(Request $request)
    {
        $this->validate($request, [
            'code' => 'required',
            'scope' => 'required',
            'context' => 'required',
        ]);

        try {
            $response = $this->httpClient->request('POST', 'https://login.bigcommerce.com/oauth2/token', [
                'json' => [
                    'client_id' => $this->getAppClientId(),
                    'client_secret' => $this->getAppSecret(),
                    'redirect_uri' => $this->baseURL . '/auth/install',
                    'grant_type' => 'authorization_code',
                    'code' => $request->input('code'),
                    'scope' => $request->input('scope'),
                    'context' => $request->input('context'),
                ]
            ]);

            $statusCode = $response->getStatusCode();
            if ($statusCode == 200) {
                $data = json_decode($response->getBody(), true);

                $store = Store::whereContext($data['context'])->firstorCreate([
                    'context' => $data['context'],
                    'access_token' => $data['access_token'],
                ]);

                User::whereBigcommerceUserId($data['user']['id'])->firstOrCreate([
                    'username' => $data['user']['username'],
                    'email' => $data['user']['email'],
                    'role' => 'owner',
                    'bigcommerce_user_id' => $data['user']['id'],
                    'store_id' => $store->id,
                ]);

                $this->storeToSession([
                    'access_token' => $store->access_token,
                    'context' => $store->context,
                    'store_hash' => str_replace('stores/', '', $store->context)
                ]);

                // App install with external link, redirect to the BigCommerce installation success page
                if ($request->has('external_install')) {
                    return redirect('https://login.bigcommerce.com/app/' . $this->getAppClientId() . '/install/succeeded');
                }

                return view('config');
            } else {
                return new Response('Something went wrong during installation', $statusCode);
            }
        } catch (RequestException $e) {
            // App install with external link, redirect to the BigCommerce installation failure page
            if ($request->has('external_install')) {
                return redirect('https://login.bigcommerce.com/app/' . $this->getAppClientId() . '/install/failed');
            } else {
                $statusCode = $e->getResponse()->getStatusCode();
                $env = app()->environment();
                if ($env === 'local' || $env === 'staging') {
                    $errorMessage = $e->getMessage();
                } else {
                    $errorMessage = 'Failed to retrieve access token from BigCommerce';
                }

                return new Response($errorMessage, $statusCode);
            }
        }
    }

    /**
     * App uninstall callback url as defined in app configuration
     */
    public function uninstall(Request $request, BigCommerceService $bigCommerceService) {
        $this->validate($request, ['signed_payload' => 'required']);
        $signedPayload = $request->input('signed_payload');

        $data = $bigCommerceService->verifySignedRequest($signedPayload);
        if ($data['user']['id'] != $data['owner']['id']) {
            return new Response('Only store owners are allowed to uninstall an app', 403);
        }

        $owner = User::whereBigcommerceUserId($data['owner']['id'])->first();
        User::whereStoreId($owner->store_id)->delete();

        return new Response('', 204);
    }
}
