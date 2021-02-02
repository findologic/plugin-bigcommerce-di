<?php

namespace Findologic\Http\Controllers;

use GuzzleHttp\Client;
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

    protected function storeToSession(array $configs) {
        foreach($configs as $key => $value) {
            Session::put($key, $value);
        }
    }
}
