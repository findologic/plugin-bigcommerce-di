<?php

namespace Controller;

use App\Http\Controllers\UserController;
use App\Models\Store;
use App\Models\User;
use App\Services\BigCommerceService;
use Illuminate\Http\Request;
use TestCase;

class UserControllerTest extends TestCase
{
    private BigCommerceService $bigCommerceServiceMock;
    private User $owner;
    private User $user;
    private Store $store;

    public function setUp(): void
    {
        parent::setUp();

        $this->bigCommerceServiceMock = $this->getMockBuilder(BigCommerceService::class)
            ->onlyMethods(['verifySignedRequest'])
            ->disableOriginalConstructor()
            ->getMock();

        $this->store = new Store([
            'context' => 'stores/test123',
            'access_token' => '1234',
        ]);
        $this->store->save();

        $this->owner = new User([
            'username' => 'Store Owner',
            'email' => 'owner@store.com',
            'role' => 'owner',
            'bigcommerce_user_id' => 1,
            'store_id' => $this->store->id
        ]);
        $this->owner->save();

        $this->user = new User([
            'username' => 'Store user',
            'email' => 'john@doe.com',
            'role' => 'user',
            'bigcommerce_user_id' => 2,
            'store_id' => $this->store->id
        ]);
        $this->user->save();
    }

    public function testStoreIsSavedWhenInstallationIsSuccessful()
    {
        $this->bigCommerceServiceMock->method('verifySignedRequest')->willReturn([
            'user' => [
                'id' => $this->user->bigcommerce_user_id,
                'username' => $this->user->username,
                'email' => $this->user->email,
            ],
            'owner' => [
                'id' => $this->owner->bigcommerce_user_id,
                'username' => $this->owner->username,
                'email' => $this->owner->email
            ],
            'context' => $this->store->context,
            'store_hash' => 'test123',
            'timestamp' => time(),
        ]);

        $userController = new UserController();
        $request = Request::create('/user/remove', 'GET', ['signed_payload' => '1234']);
        $userController->remove($request, $this->bigCommerceServiceMock);

        // User from remove callback should be gone and owner still exists
        $user = User::where('id', $this->user->id)->first();
        $this->assertNull($user);
        $owner = User::where('id', $this->owner->id)->first();
        $this->assertInstanceOf(User::class, $owner);
    }
}
