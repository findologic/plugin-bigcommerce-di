<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\BigCommerceService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class UserController extends Controller
{
    /**
     * Remove user callback url as defined in app configuration
     */
    public function remove(Request $request, BigCommerceService $bigCommerceService)
    {
        $this->validate($request, ['signed_payload' => 'required']);
        $signedPayload = $request->input('signed_payload');

        $data = $bigCommerceService->verifySignedRequest($signedPayload);
        User::whereBigcommerceUserId($data['user']['id'])->delete();

        return new Response('', 204);
    }
}
