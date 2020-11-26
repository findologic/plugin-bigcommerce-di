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

$router->get('/', ['uses' => 'AuthController@index']);

$router->group(['prefix' => 'auth'], function () use ($router) {

    $router->get('install', ['uses' => 'AuthController@install']);

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

$router->post('/config', ['uses' => 'AuthController@handleConfiguration']);
