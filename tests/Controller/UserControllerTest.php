<?php

namespace Controller;

use App\Http\Controllers\UserController;
use App\Models\Store;
use App\Models\User;
use App\Services\BigCommerceService;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;

class UserControllerTest extends TestCase
{
    private BigCommerceService $bigCommerceServiceMock;

    public function setUp(): void
    {
        parent::setUp();

        $this->bigCommerceServiceMock = $this->getMockBuilder(BigCommerceService::class)
            ->onlyMethods(['verifySignedRequest'])
            ->disableOriginalConstructor()
            ->getMock();
    }

    public function testStoreIsSavedWhenInstallationIsSuccessful()
    {
        $store = new Store([
            'context' => 'stores/test123',
            'access_token' => '1234'
        ]);
        $store->save();

        $user = new User([
            'username' => 'John Doe',
            'email' => 'john@doe.com',
            'role' => 'user',
            'bigcommerce_user_id' => 125689,
            'store_id' => $store->id
        ]);
        $user->save();

        $this->bigCommerceServiceMock->method('verifySignedRequest')->willReturn([
            'user' => [
                'id' => 125689,
                'username' => 'John Doe',
                'email' => 'john@doe.com'
            ],
            'owner' => [
                'id' => 125689,
                'username' => 'John Doe',
                'email' => 'john@doe.com'
            ],
            'context' => 'stores/test123',
            'store_hash' => 'test123',
            'timestamp' => time(),
        ]);

        $userController = new UserController();
        $request = Request::create('/user/remove', 'GET', ['signed_payload' => '1234']);
        $userController->remove($request, $this->bigCommerceServiceMock);

        $user = User::where('id', $user->id)->first();
        $this->assertCount(0, $user);
    }
}
