<?php

namespace App\Http\Controllers;

use GuzzleHttp\Client;
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
}
