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
        $data = null;
        if (strpos($signedPayload, '.') !== false) {
            list($encodedData, $encodedSignature) = explode('.', $signedPayload, 2);

            $signature = base64_decode($encodedSignature);
            $rawJson = base64_decode($encodedData);
            $data = json_decode($rawJson, true);
            $this->validateSignature($signature, $rawJson);

            if (!isset($data['owner']) || !isset($data['user']) || !isset($data['context'])) {
                throw new Exception('The signed request from BigCommerce has missing data!');
            }
        }

        return $data;
    }

    public function validateSignature(string $signature, string $rawJson): void {
        $expectedSignature = hash_hmac('sha256', $rawJson, env('CLIENT_SECRET'), $raw = false);
        if (!hash_equals($expectedSignature, $signature)) {
            throw new Exception('Bad signed request from BigCommerce!');
        }
    }

    public function getStoreSettings(): Collection
    {
        $endpoint = sprintf('stores/%s/v2/store', $this->getStoreHash());
        $xmlResponse = $this->sendBigCommerceAPIRequest('GET', $endpoint);

        return $this->createCollectionFromXml($xmlResponse);
    }

    public function deleteExistingScript(): void {
        $script = Script::where('store_hash', $this->getStoreHash())->first();
        if ($script) {
            $uuid = $script['uuid'];
            $endpoint = sprintf('stores/%s/v3/content/scripts/%s', $this->getStoreHash(), $uuid);
            $this->sendBigCommerceAPIRequest('DELETE', $endpoint);
            $script->delete();
        }
    }

    public function createScript(Config $config): void
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

        $endpoint = sprintf('stores/%s/v3/content/scripts',  $this->getStoreHash());
        $response = $this->sendBigCommerceAPIRequest('POST', $endpoint, json_encode($requestBody));

        $collection = collect(json_decode($response->getBody(), true));
        $data = $collection['data'];

        $script = new Script();
        $script->store_id = $config->store_id;
        $script->name = $data['name'];
        $script->uuid = $data['uuid'];
        $script->store_hash = $this->getStoreHash();
        $script->save();
    }

    private function sendBigCommerceAPIRequest($method, $endpoint, $body = ''): ResponseInterface
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

    private function createCollectionFromXml(ResponseInterface $xmlResponse): Collection
    {
        $collection = new Collection();
        if ($xmlResponse->getStatusCode() >= 200 && $xmlResponse->getStatusCode() < 300) {
            $xml = simplexml_load_string($xmlResponse->getBody(), 'SimpleXMLElement', LIBXML_NOCDATA);

            $json = json_encode($xml);
            $array = json_decode($json, true);
            $collection = collect($array);
        }

        return $collection;
    }

    private function getAccessToken(): ?string
    {
        return Session::get('access_token');
    }

    private function getStoreHash(): ?string
    {
        return Session::get('store_hash');
    }
}
