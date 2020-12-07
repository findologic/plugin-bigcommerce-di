<?php

namespace App\Http\Controllers;

use App\Models\Config;
use App\Models\Script;
use App\Models\Store;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Session;
use Psr\Http\Message\ResponseInterface;


class AuthController extends Controller
{
    private $httpClient;
    private $baseURL;

    public function __construct($httpClient = null)
    {
        $this->httpClient = $httpClient ?? new Client();
        $this->baseURL = env('APP_URL');
    }

    public function index()
    {
        return new Response('github/plugin-bigcommerce-di');
    }

    public function install(Request $request)
    {
        // Make sure all required query params have been passed
        if (!$request->has('code') || !$request->has('scope') || !$request->has('context')) {
            $errorMessage = 'Not enough information was passed to install this app';
            return new Response($errorMessage, '400');
        }

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
            // Inside data you have access_token, context, userId, userEmail
            $data = json_decode($response->getBody(), true);
            if ($statusCode == 200) {
                $store = Store::where('domain', $data['context'])->first();
                if (isset($store['id'])) {
                    $store->delete();
                }
                //Saving access token in database
                $store = new Store();
                $store->domain = $data['context'];
                $store->access_token = $data['access_token'];
                $store->save();

                // If the merchant installed the app via an external link, redirect back to the
                // BC installation success page for this app
                if ($request->has('external_install')) {
                    return redirect('https://login.bigcommerce.com/app/' . $this->getAppClientId() . '/install/succeeded');
                }

                return new Response('Findologic App is successfully installed. Please refresh the page');
            } else {
                $errorMessage = 'Something went wrong during installation';
                return new Response($errorMessage, $statusCode);
            }
        } catch (RequestException $e) {
            // If the merchant installed the app via an external link, redirect back to the
            // BC installation failure page for this app
            if ($request->has('external_install')) {
                return redirect('https://login.bigcommerce.com/app/' . $this->getAppClientId() . '/install/failed');
            } else {
                $statusCode = $e->getResponse()->getStatusCode();
                $errorMessage = 'Failed to retrieve access token from BigCommerce';
                return new Response($errorMessage, $statusCode);
            }
        }
    }

    public function load(Request $request)
    {
        $signedPayload = $request->input('signed_payload');

        if (!empty($signedPayload)) {
            $verifiedSignedRequestData = $this->verifySignedRequest($signedPayload);
            if ($verifiedSignedRequestData !== null) {
                $store = Store::where('domain', $verifiedSignedRequestData['context'])->first();
                Session::put('access_token', $store['access_token']);
                Session::put('context', $verifiedSignedRequestData['context']);
                Session::put('store_hash', $verifiedSignedRequestData['store_hash']);

                $store_id = $store['id'];
                $configRow = Config::where('store_id', $store_id)->first();
                if (isset($configRow['id'])) {
                    $config = Config::find($configRow['id']);
                    if ($config->active > 0) {
                        $active_status = $config->active;
                    } else {
                        $active_status = null;
                    }

                    $viewData = [
                        'shopkey' => $config->shopkey,
                        'active_status' => $active_status
                    ];
                    return view('app', $viewData);
                }

                return view('app');

            } else {
                $errorMessage = 'The signed request from BigCommerce could not be validated';
                return new Response($errorMessage, 400);
            }
        } else {
            $errorMessage = 'The signed request from BigCommerce was empty';
            return new Response($errorMessage, 400);
        }

    }

    public function verifySignedRequest($signedRequest)
    {
        if (strpos($signedRequest, '.') !== false) {

            list($encodedData, $encodedSignature) = explode('.', $signedRequest, 2);

            // decode the data
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

    private function makeBigCommerceAPIRequest($method, $endpoint, $body = ''): ResponseInterface
    {
        $requestConfig = [
            'headers' => [
                'X-Auth-Client' => $this->getAppClientId(),
                'X-Auth-Token' => $this->getAccessToken(),
                'Content-Type' => 'application/json',
            ]
        ];

        if (!empty($body)) {
            $requestConfig['body'] = $body;
        }

        try {
            return $this->httpClient->request($method, 'https://api.bigcommerce.com/' . $endpoint, $requestConfig);
        } catch (RequestException $e) {
            return $e->getResponse();
        }
    }

    private function createCollectionFromXml($xmlResponse): Collection
    {
        if ($xmlResponse->getStatusCode() >= 200 && $xmlResponse->getStatusCode() < 300) {
            $xml = simplexml_load_string($xmlResponse->getBody(), 'SimpleXMLElement', LIBXML_NOCDATA);
            $json = json_encode($xml);
            $array = json_decode($json, true);
            $collection = collect($array);

            return $collection;
        } else {
            return new Collection();
        }
    }

    public function handleConfiguration(Request $request)
    {
        if ($request->has('store_hash') && $request->has('access_token') && $request->has('context') && $request->has('shopkey')) {
            $store_hash = $request->input('store_hash');
            $access_token = $request->input('access_token');
            $context = $request->input('context');
            $shopkey = $request->input('shopkey');
            $active_status = ($request->input('active_status') == '') ? null : true;
            $store = Store::where('domain', $context)->first();
            $store_id = $store['id'];

            $configRow = Config::where('store_id', $store_id)->first();
            if (isset($configRow['id'])) {
                $config = Config::find($configRow['id']);
                $config->active = isset($active_status) ? true : false;
                $config->store_id = $store_id;
                $config->shopkey = $shopkey;
                $config->save();
            } else {
                $config = new Config;
                $config->active = isset($active_status) ? true : false;
                $config->store_id = $store_id;
                $config->shopkey = $shopkey;
                $config->save();
            }

            Session::put('access_token', $access_token);
            Session::put('context', $context);
            Session::put('store_hash', $store_hash);
            Session::put('saved', true);

            $xmlResponse = $this->makeBigCommerceAPIRequest(
                'GET',
                'stores/' . $this->getStoreHash() . '/v2/store'
            );
            $collection = $this->createCollectionFromXml($xmlResponse);

            if (isset($collection['features']['stencil_enabled'])) {
                // Deleting old script when ever someone save settings
                $scriptRow = Script::where('store_hash', $store_hash)->first();
                if ($scriptRow['id']) {
                    $uuid = $scriptRow['uuid'];
                    $this->makeBigCommerceAPIRequest(
                        'DELETE',
                        'stores/' . $this->getStoreHash() . '/v3/content/scripts/' . $uuid
                    );
                    $scriptRow->delete();
                } else {
                    // checking that active button is checked then add script
                    if (isset($active_status)) {

                        $jsSnippet = view('jsSnippet', [
                            'shopkey' => $shopkey
                        ]);

                        $requestBody = [
                            'name' => 'Findologic',
                            'description' => 'Search & Navigation Platform',
                            'html' => $jsSnippet->render(),
                            'auto_uninstall' => true,
                            'load_method' => 'default',
                            'location' => 'head',
                            'visibility' => 'all_pages',
                            'kind:' => 'script_tag',
                            'consent_category' => 'essential'
                        ];

                        $response = $this->makeBigCommerceAPIRequest(
                            'POST',
                            'stores/' . $this->getStoreHash() . '/v3/content/scripts',
                            json_encode($requestBody)
                        );
                        // converting data
                        $body = $response->getBody()->getContents();
                        $collection = collect(json_decode($body, true));
                        $data = $collection['data'];
                        // saving in database
                        $script = new Script;
                        $script->name = $data['name'];
                        $script->uuid = $data['uuid'];
                        $script->store_hash = $store_hash;
                        $script->save();
                    }
                }

            }

            $viewData = ['shopkey' => $shopkey, 'active_status' => $active_status];
            return view('app', $viewData);
        } else {
            return 'Error: Not enough data';
        }
    }

    private function getAccessToken()
    {
        return Session::get('access_token');
    }

    private function getStoreHash()
    {
        return Session::get('store_hash');
    }

    private function getAppClientId()
    {
        return env('CLIENT_ID');
    }

    private function getAppSecret()
    {
        return env('CLIENT_SECRET');
    }
}
