<?php

namespace App\Http\Controllers;

use Exception;
use GuzzleHttp\Client;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Laravel\Lumen\Routing\Controller as BaseController;

class Controller extends BaseController
{
    protected $httpClient;
    protected $baseURL;

    public function __construct($httpClient = null)
    {
        $this->httpClient = $httpClient ?? new Client();
        $this->baseURL = env('APP_URL');
    }

    protected function getAppClientId()
    {
        return env('CLIENT_ID');
    }

    protected function getAppSecret()
    {
        return env('CLIENT_SECRET');
    }

    /**
     * @param array<string, mixed> $configs
     */
    protected function storeToSession(array $configs) {
        foreach($configs as $key => $value) {
            Session::put($key, $value);
        }
    }

    /**
     * @param $signedRequest
     * @return mixed|null
     */
    protected function verifySignedRequest($signedRequest)
    {
        if (strpos($signedRequest, '.') !== false) {

            list($encodedData, $encodedSignature) = explode('.', $signedRequest, 2);

            $signature = base64_decode($encodedSignature);
            $jsonStr = base64_decode($encodedData);
            $data = json_decode($jsonStr, true);

            // confirm the signature
            $expectedSignature = hash_hmac('sha256', $jsonStr, $this->getAppSecret(), $raw = false);
            if (!hash_equals($expectedSignature, $signature)) {
                Log::error('Bad signed request from BigCommerce!');
                return null;
            }

            if (!isset($data['owner']) || !isset($data['user']) || !isset($data['context'])) {
                 throw new Exception('The signed request from BigCommerce has missing data!');
            }

            return $data;
        } else {
            return null;
        }
    }
}
