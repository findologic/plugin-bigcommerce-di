<?php

namespace Findologic\Http\Controllers;

use Findologic\Models\Config;
use Findologic\Models\Script;
use Findologic\Models\Store;
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
        $store = Store::where('context', $context)->first();

        if (isset($store['id'])) {
            $storeId = $store['id'];
        } else {
            return new Response('Store is not available', 400);
        }

        $configRow = Config::where('store_id', $storeId)->first();
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

        $this->addSnippetToStore($storeHash, $config);

        return view('app', [
            'shopkey' => $shopkey,
            'active_status' => $activeStatus
        ]);
    }

    private function addSnippetToStore(string $storeHash, Config $config)
    {
        $xmlResponse = $this->makeBigCommerceAPIRequest(
            'GET',
            'stores/' . $this->getStoreHash() . '/v2/store'
        );

        $collection = $this->createCollectionFromXml($xmlResponse);

        if (isset($collection['features']['stencil_enabled'])) {
            // Delete any previously added script
            $scriptRow = Script::where('store_hash', $storeHash)->first();
            if ($scriptRow) {
                $uuid = $scriptRow['uuid'];
                $this->makeBigCommerceAPIRequest(
                    'DELETE',
                    'stores/' . $this->getStoreHash() . '/v3/content/scripts/' . $uuid
                );
                $scriptRow->delete();
            }

            // Add new snippet with fresh config
            if ($config->active) {
                $jsSnippet = view('jsSnippet', [
                    'shopkey' => $config->shopkey
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

                $collection = collect(json_decode($response->getBody(), true));
                $data = $collection['data'];

                $script = new Script();
                $script->store_id = $config->store_id;
                $script->name = $data['name'];
                $script->uuid = $data['uuid'];
                $script->store_hash = $storeHash;
                $script->save();
            }
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

    private function getAccessToken()
    {
        return Session::get('access_token');
    }

    private function getStoreHash()
    {
        return Session::get('store_hash');
    }
}
