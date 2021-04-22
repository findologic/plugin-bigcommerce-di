<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class UserController extends Controller
{
    /**
     * Remove user callback url as defined in app configuration
     */
    public function remove(Request $request)
    {
        $this->validate($request, ['signed_payload' => 'required']);
        $signedPayload = $request->input('signed_payload');

        $data = $this->verifySignedRequest($signedPayload);
        User::where('bigcommerce_user_id', $data['user']['id'])->delete();

        return new Response('', 204);
    }
}
