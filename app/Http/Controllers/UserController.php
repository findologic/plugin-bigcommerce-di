<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;

class UserController extends Controller
{
    public function create(Request $request)
    {
        return new Response();
    }

    /**
     * Remove user callback url defined in app configuration
     */
    public function remove(Request $request)
    {
        // TODO: Remove user
        var_dump($request);
    }
}
