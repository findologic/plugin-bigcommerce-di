<?php

namespace App\Services;

use App\Models\Config;
use App\Models\Script;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Session;
use Psr\Http\Message\ResponseInterface;

class BigCommerceService
{
    private Client $httpClient;

    public function __construct(Client $httpClient)
    {
        $this->httpClient = $httpClient;
    }

    public function verifySignedRequest(string $signedPayload): ?array
    {
        if (strpos($signedPayload, '.') !== false) {

            list($encodedData, $encodedSignature) = explode('.', $signedPayload, 2);

            $signature = base64_decode($encodedSignature);
            $jsonStr = base64_decode($encodedData);
            $data = json_decode($jsonStr, true);
            $this->validateSignature($signature, $jsonStr);

            if (!isset($data['owner']) || !isset($data['user']) || !isset($data['context'])) {
                throw new Exception('The signed request from BigCommerce has missing data!');
            }

            return $data;
        } else {
            return null;
        }
    }

    public function validateSignature($signature, $jsonStr) {
        $expectedSignature = hash_hmac('sha256', $jsonStr, env('CLIENT_SECRET'), $raw = false);
        if (!hash_equals($expectedSignature, $signature)) {
            throw new Exception('Bad signed request from BigCommerce!');
        }
    }

    public function getStoreSettings(): Collection
    {
        $xmlResponse = $this->makeBigCommerceAPIRequest(
            'GET',
            'stores/' . $this->getStoreHash() . '/v2/store'
        );
        return $this->createCollectionFromXml($xmlResponse);
    }

    public function deleteExistingScript() {
        $scriptRow = Script::where('store_hash', $this->getStoreHash())->first();
        if ($scriptRow) {
            $uuid = $scriptRow['uuid'];
            $this->makeBigCommerceAPIRequest(
                'DELETE',
                'stores/' . $this->getStoreHash() . '/v3/content/scripts/' . $uuid
            );
            $scriptRow->delete();
        }
    }

    public function createScript(Config $config)
    {
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
        $script->store_hash = $this->getStoreHash();
        $script->save();
    }

    private function makeBigCommerceAPIRequest($method, $endpoint, $body = ''): ResponseInterface
    {
        $requestConfig = [
            'headers' => [
                'X-Auth-Client' => env('CLIENT_ID'),
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
