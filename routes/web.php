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
    $router->get('load', ['uses' => 'AuthController@load']);
    $router->get('install', ['uses' => 'AuthController@install']);
    $router->get('uninstall', ['uses' => 'AuthController@uninstall']);
});

$router->group(['prefix' => 'user'], function () use ($router) {
    $router->get('create', ['uses' => 'UserController@create']);
    $router->get('remove', ['uses' => 'UserController@remove']);
});

$router->post('/config', ['uses' => 'ConfigController@handleConfiguration']);
