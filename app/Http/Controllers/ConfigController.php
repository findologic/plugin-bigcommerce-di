<?php

namespace App\Http\Controllers;

use App\Models\Config;
use App\Models\Script;
use App\Models\Store;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Session;
use Psr\Http\Message\ResponseInterface;

class ConfigController extends Controller
{
    public function handleConfiguration(Request $request)
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
        $store = Store::where('domain', $context)->first();

        if (isset($store['id'])) {
            $storeId = $store['id'];
        } else {
            return new Response('Store is not available', 400);
        }

        $configRow = Config::where('store_id', $storeId)->first();
        if (isset($configRow['id'])) {
            $config = Config::find($configRow['id']);
            $config->active = isset($activeStatus) ? true : false;
            $config->store_id = $storeId;
            $config->shopkey = $shopkey;
            $config->save();
        } else {
            $config = new Config;
            $config->active = isset($activeStatus) ? true : false;
            $config->store_id = $storeId;
            $config->shopkey = $shopkey;
            $config->save();
        }

        Session::put('access_token', $accessToken);
        Session::put('context', $context);
        Session::put('store_hash', $storeHash);
        Session::put('saved', true);

        $xmlResponse = $this->makeBigCommerceAPIRequest(
            'GET',
            'stores/' . $this->getStoreHash() . '/v2/store'
        );
        $collection = $this->createCollectionFromXml($xmlResponse);

        if (isset($collection['features']['stencil_enabled'])) {
            // Deleting old script when ever someone save settings
            $scriptRow = Script::where('store_hash', $storeHash)->first();
            if ($scriptRow) {
                $uuid = $scriptRow['uuid'];
                $this->makeBigCommerceAPIRequest(
                    'DELETE',
                    'stores/' . $this->getStoreHash() . '/v3/content/scripts/' . $uuid
                );
                $scriptRow->delete();
            } else {
                // checking that active button is checked then add script
                if (isset($activeStatus)) {
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
                        'visibility' => 'storefront',
                        'kind' => 'script_tag',
                        'consent_category' => 'essential',
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
                    $script->store_hash = $storeHash;
                    $script->save();
                }
            }

        }

        return view('app', [
            'shopkey' => $shopkey,
            'active_status' => $activeStatus
        ]);
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

    private function getAccessToken()
    {
        return Session::get('access_token');
    }

    private function getStoreHash()
    {
        return Session::get('store_hash');
    }
}
