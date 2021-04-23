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

        var_dump($signedPayload);
        $data = $bigCommerceService->verifySignedRequest($signedPayload);
        $store = Store::where('context', $data['context'])->first();
        if (!$store) {
            return new Response('Error: Store could not be found', 400);
        }

        $user = User::where('bigcommerce_user_id', $data['user']['id'])->first();
        if (!$user) {
            $user = new User();
            $user->email = $data['user']['email'];
            $user->role = 'user';
            $user->bigcommerce_user_id = $data['user']['id'];
            $user->store_id = $store->id;
            $user->save();
        }

        $this->storeToSession([
            'access_token' => $store['access_token'],
            'context' => $data['context'],
            'store_hash' => $data['store_hash']
        ]);

        $store_id = $store['id'];
        $configRow = Config::where('store_id', $store_id)->first();

        $viewData = [];
        if (isset($configRow['id'])) {
            $config = Config::find($configRow['id']);
            if ($config->active > 0) {
                $activeStatus = $config->active;
            } else {
                $activeStatus = null;
            }
            $viewData = [
                'shopkey' => $config->shopkey,
                'active_status' => $activeStatus
            ];
        }

        return view('app', $viewData);
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
            // Response contains access_token, context, userId, userEmail
            $data = json_decode($response->getBody(), true);

            if ($statusCode == 200) {
                $store = Store::where('context', $data['context'])->first() ?? new Store();
                $store->context = $data['context'];
                $store->access_token = $data['access_token'];
                $store->save();

                $user = User::where('bigcommerce_user_id', $data['user']['id'])->first();
                if (!$user) {
                    $user = new User();
                    $user->username = $data['user']['username'];
                    $user->email = $data['user']['email'];
                    $user->role = 'owner';
                    $user->bigcommerce_user_id = $data['user']['id'];
                    $user->store_id = $store->id;
                    $user->save();
                }

                $this->storeToSession([
                    'access_token' => $store->access_token,
                    'context' => $store->context,
                    'store_hash' => str_replace('stores/', '', $store->context)
                ]);

                // App install with external link, redirect to the BigCommerce installation success page
                if ($request->has('external_install')) {
                    return redirect('https://login.bigcommerce.com/app/' . $this->getAppClientId() . '/install/succeeded');
                }

                return view('app');
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

        $owner = User::where('bigcommerce_user_id', $data['owner']['id'])->first();
        User::where('store_id', $owner->store_id)->delete();

        return new Response('', 204);
    }
}
