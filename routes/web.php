<?php

/** @var \Laravel\Lumen\Routing\Router $router */

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It is a breeze. Simply tell Lumen the URIs it should respond to
| and give it the Closure to call when that URI is requested.
|
*/

//$router->get('/', function () use ($router) {
//    return '<h3>Welcome to Findologic</h3>';
//});

$router->get('/', ['uses' => 'AuthController@index']);

$router->get('auth/hello-world', ['uses' => 'AuthController@helloWorld']);

$router->group(['prefix' => 'auth'], function () use ($router) {

    $router->get('install', ['uses' => 'AuthController@insatll']);

    $router->get('load', ['uses' => 'AuthController@load']);

    $router->get('uninstall', function (){
        echo 'uninstall';
        return app()->version();
    });

    $router->get('remove-user', function (){
        echo 'remove-user';
        return app()->version();
    });


});

//$router->get('bc-api/v3/catalog/summary', ['uses' => 'AuthController@normallBigCommerceAPIRequest']);

//$router->addRoute(['GET','POST', 'PUT', 'PATCH', 'DELETE','OPTIONS'], '/bc-api/{endpoint:[v2\/.*|v3\/.*]+}', ['uses' => 'AuthController@proxyBigCommerceAPIRequest']);
