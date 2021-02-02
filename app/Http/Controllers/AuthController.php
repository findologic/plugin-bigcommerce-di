<?php

namespace Findologic\Http\Controllers;

use Findologic\Models\Config;
use Findologic\Models\Store;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AuthController extends Controller
{
    public function index()
    {
        return new Response('github/plugin-bigcommerce-di');
    }

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
                $store = Store::where('context', $data['context'])->first();
                if (isset($store['id'])) {
                    $store->delete();
                }

                $store = new Store();
                $store->context = $data['context'];
                $store->access_token = $data['access_token'];
                $store->save();

                $this->storeToSession([
                    'access_token' => $data['access_token'],
                    'context' => $data['context'],
                    'store_hash' => str_replace('stores/', '', $data['context'])
                ]);

                // App install with external link, redirect to the BigCommerce installation success page
                if ($request->has('external_install')) {
                    return redirect('https://login.bigcommerce.com/app/' . $this->getAppClientId() . '/install/succeeded');
                }

                return view('app');
            } else {
                $errorMessage = 'Something went wrong during installation';
                return new Response($errorMessage, $statusCode);
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

    public function load(Request $request)
    {
        $this->validate($request, ['signed_payload' => 'required']);
        $signedPayload = $request->input('signed_payload');

        $verifiedSignedRequestData = $this->verifySignedRequest($signedPayload);
        if (!$verifiedSignedRequestData) {
            $errorMessage = 'Error: The signed request from BigCommerce could not be validated';
            return new Response($errorMessage, 400);
        } else {
            $store = Store::where('context', $verifiedSignedRequestData['context'])->first();
            if (!$store) {
                $errorMessage = 'Error: Store could not be found';
                return new Response($errorMessage, 400);
            }

            $this->storeToSession([
                'access_token' => $store['access_token'],
                'context' => $verifiedSignedRequestData['context'],
                'store_hash' => $verifiedSignedRequestData['store_hash']
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
    }

    public function verifySignedRequest($signedRequest)
    {
        if (strpos($signedRequest, '.') !== false) {

            list($encodedData, $encodedSignature) = explode('.', $signedRequest, 2);

            $signature = base64_decode($encodedSignature);
            $jsonStr = base64_decode($encodedData);
            $data = json_decode($jsonStr, true);

            // confirm the signature
            $expectedSignature = hash_hmac('sha256', $jsonStr, $this->getAppSecret(), $raw = false);
            if (!hash_equals($expectedSignature, $signature)) {
                error_log('Bad signed request from BigCommerce!');
                return null;
            }

            return $data;
        } else {
            return null;
        }
    }
}
