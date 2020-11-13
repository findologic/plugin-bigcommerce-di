<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use GuzzleHttp\Psr7;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Session;


class AuthController extends Controller
{
    protected $baseURL;

    public function __construct()
    {
        $this->baseURL = env('APP_URL');
    }

    public function index()
    {
        //Session::put('name', "isfhan Ahmed");
        //return '<h3>Welcome to Findologic controller</h3> <br> <a href="auth/hello-world">click here</a>';
        return "<h3>Welcome to Findologic controller</h3>";
    }

    public function getAppClientId()
    {
        return env('DEV_CLIENT_ID');
    }

    public function getAppSecret(Request $request)
    {
        return env('DEV_CLIENT_SECRET');
    }

    public function getAccessToken(Request $request)
    {
        return Session::get('access_token');
    }


    public function getStoreHash(Request $request)
    {
        return Session::get('store_hash');
    }


    public function helloWorld(Request $request)
    {
        //return 'Hello World!'.Session::get('name')."";
        return 'Hello World!';
    }

    public function insatll(Request $request)
    {
        //return 'insatll route hit';

        // Make sure all required query params have been passed
        if (!$request->has('code') || !$request->has('scope') || !$request->has('context')) {
            $errorMessage = "Not enough information was passed to install this app.";
            echo '<h4>An issue has occurred:</h4> <p>' . $errorMessage . '</p> <a href="'.$this->baseURL.'">Go back to home</a>';
        }

        try {
            $client = new Client();
            $result = $client->request('POST', 'https://login.bigcommerce.com/oauth2/token', [
                'json' => [
                    'client_id' => $this->getAppClientId(),
                    'client_secret' => $this->getAppSecret($request),
                    'redirect_uri' => $this->baseURL . '/auth/install',
                    'grant_type' => 'authorization_code',
                    'code' => $request->input('code'),
                    'scope' => $request->input('scope'),
                    'context' => $request->input('context'),
                ]
            ]);

            $statusCode = $result->getStatusCode();
            // Inside data you have access_token,context,userId,userEmail
            $data = json_decode($result->getBody(), true);

            if ($statusCode == 200) {
                Session::put('store_hash', $data['context']);
                Session::put('access_token', $data['access_token']);
                Session::put('user_id', $data['user']['id']);
                Session::put('user_email', $data['user']['email']);

                // If the merchant installed the app via an external link, redirect back to the
                // BC installation success page for this app
                if ($request->has('external_install')) {
                    return redirect('https://login.bigcommerce.com/app/' . $this->getAppClientId() . '/install/succeeded');
                }
            }

            dd($data);
            return redirect('/');
        } catch (RequestException $e) {
            $statusCode = $e->getResponse()->getStatusCode();
            $errorMessage = "An error occurred.";

            if ($e->hasResponse()) {
                if ($statusCode != 500) {
                    $errorMessage = Psr7\str($e->getResponse());
                }
            }
            // If the merchant installed the app via an external link, redirect back to the
            // BC installation failure page for this app
            if ($request->has('external_install')) {
                return redirect('https://login.bigcommerce.com/app/' . $this->getAppClientId() . '/install/failed');
            } else {
                //return redirect()->action('AuthController@error')->with('error_message', $errorMessage);
                echo '<h4>Error:</h4> <p>' . $errorMessage . '</p> <a href="'.$this->baseURL.'">Go back to home</a>';
            }

        }

    }

    public function load(Request $request)
    {
        $signedPayload = $request->input('signed_payload');

        if (!empty($signedPayload)) {
            $verifiedSignedRequestData = $this->verifySignedRequest($signedPayload, $request);
            if ($verifiedSignedRequestData !== null) {
                Session::put('user_id', $verifiedSignedRequestData['user']['id']);
                Session::put('user_email', $verifiedSignedRequestData['user']['email']);
                Session::put('owner_id', $verifiedSignedRequestData['owner']['id']);
                Session::put('owner_email', $verifiedSignedRequestData['owner']['email']);
                Session::put('store_hash', $verifiedSignedRequestData['context']);
            } else {
                $errorMessage = "The signed request from BigCommerce could not be validated.";
                echo '<h4>An issue has occurred:</h4> <p>' . $errorMessage . '</p> <a href="'.$this->baseURL.'">Go back to home</a>';
            }
        } else {
            $errorMessage = "The signed request from BigCommerce was empty.";
            echo '<h4>An issue has occurred:</h4> <p>' . $errorMessage . '</p> <a href="'.$this->baseURL.'">Go back to home</a>';
        }

        
        dd($verifiedSignedRequestData);

        //return redirect('/');
        //return 'load route hit';
    }

        ######## Ignore this i am working on this Area ############

//    private function verifySignedRequest($signedRequest, $appRequest)
//    {
//        list($encodedData, $encodedSignature) = explode('.', $signedRequest, 2);
//
//        // decode the data
//        $signature = base64_decode($encodedSignature);
//        $jsonStr = base64_decode($encodedData);
//        $data = json_decode($jsonStr, true);
//
//        // confirm the signature
//        $expectedSignature = hash_hmac('sha256', $jsonStr, $this->getAppSecret($appRequest), $raw = false);
//        if (!hash_equals($expectedSignature, $signature)) {
//            error_log('Bad signed request from BigCommerce!');
//            return null;
//        }
//        return $data;
//    }

//    public function makeBigCommerceAPIRequest(Request $request, $endpoint)
//    {
//        $requestConfig = [
//            'headers' => [
//                'X-Auth-Client' => $this->getAppClientId(),
//                'X-Auth-Token'  => $this->getAccessToken($request),
//                'Content-Type'  => 'application/json',
//            ]
//        ];
//
//        if ($request->method() === 'PUT') {
//            $requestConfig['body'] = $request->getContent();
//        }
//
//        $client = new Client();
//        $result = $client->request($request->method(), 'https://api.bigcommerce.com/' . $this->getStoreHash($request) . '/' . $endpoint, $requestConfig);
//        return $result;
//    }
//
//    public function proxyBigCommerceAPIRequest(Request $request, $endpoint)
//    {
//        if (strrpos($endpoint, 'v2') !== false) {
//            // For v2 endpoints, add a .json to the end of each endpoint, to normalize against the v3 API standards
//            $endpoint .= '.json';
//        }
//        $result = $this->makeBigCommerceAPIRequest($request, $endpoint);
//
//        return response($result->getBody(), $result->getStatusCode())->header('Content-Type', 'application/json');
//    }


}
