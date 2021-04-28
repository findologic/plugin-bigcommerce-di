<?php

namespace Controller;

use App\Http\Controllers\AuthController;
use App\Models\Store;
use App\Models\User;
use App\Services\BigCommerceService;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use TestCase;

class AuthControllerTest extends TestCase
{
    private BigCommerceService $bigCommerceServiceMock;

    public function setUp(): void
    {
        $this->bigCommerceServiceMock = $this->getMockBuilder(BigCommerceService::class)
            ->onlyMethods(['verifySignedRequest'])
            ->disableOriginalConstructor()
            ->getMock();

        parent::setUp();
    }

    public function installParameterProvider()
    {
        return [
            'No code provided' => [
                'code' => null,
                'scope' => 'test scope',
                'context' => 'test context'
            ],
            'No scope provided' => [
                'code' => 'test code',
                'scope' => null,
                'context' => 'test context'
            ],
            'No context provided' => [
                'code' => 'test code',
                'scope' => 'test scope',
                'context' => null
            ]
        ];
    }

    /**
     * @dataProvider installParameterProvider
     */
    public function testInstallActionThrowsErrorWhenRequiredParamsAreMissing($code, $scope, $context)
    {
        $this->expectException(ValidationException::class);

        $parameters = [
            'code' => $code,
            'scope' => $scope,
            'context' => $context
        ];
        $request = Request::create('/auth/install', 'GET', array_filter($parameters));
        $authController = new AuthController();
        $authController->install($request);
    }

    public function testStoreAndUserIsSavedWhenInstallationIsSuccessful()
    {
        $mockedResponse = [
            'access_token' => 'test access token',
            'scope' => 'test scope',
            'context' => 'stores/test123',
            'user' => array(
                'id' => 123,
                'username' => 'Store Owner',
                'email' => 'owner@store.com'
            ),
        ];

        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], json_encode($mockedResponse))
        ]);
        $handler = HandlerStack::create($mock);
        $httpClientMock = new Client(['handler' => $handler]);
        $authController = new AuthController($httpClientMock);

        $parameters = [
            'code' => 'test code',
            'scope' => 'stores/test123',
            'context' => 'test context'
        ];
        $request = Request::create('/auth/install', 'GET', $parameters);
        /** @var View $view */
        $view = $authController->install($request);
        $this->assertEquals('app', $view->getName());

        $store = Store::where('context', $mockedResponse['context'])->first();
        $this->assertSame($mockedResponse['context'], $store->context);
        $this->assertSame($mockedResponse['access_token'], $store->access_token);

        $user = User::where('bigcommerce_user_id', 123)->first();
        $this->assertInstanceOf(User::class, $user);
    }

    public function testErrorAppearsWhenRequestExceptionHappenOnInstall()
    {
        $request = Request::create('/auth/install', 'GET', [
            'code' => 'test code',
            'scope' => 'stores/test123',
            'context' => 'test context'
        ]);
        $authController = new AuthController();
        $response = $authController->install($request);

        $this->assertSame(400, $response->getStatusCode());
        $expectedMsg = 'Failed to retrieve access token from BigCommerce';
        $this->assertSame($expectedMsg, $response->getContent());
    }

    public function testErrorAppearsWhenSignedPayloadIsNotSet()
    {
        $this->expectException(ValidationException::class);

        $request = Request::create('/auth/load', 'GET', [
            'signed_payload' => '',
        ]);
        $authController = new AuthController();
        $authController->load($request, $this->bigCommerceServiceMock);
    }

    public function testVerifiedSignedRequestDataIsSavedInSession()
    {
        $this->setupDefaultBigCommerceServiceMock();

        $request = Request::create('/auth/load', 'GET', [
            'signed_payload' => '123dummy'
        ]);
        $authController = new AuthController();
        $authController->load($request, $this->bigCommerceServiceMock);

        $this->assertEquals('test access token', Session::get('access_token'));
        $this->assertEquals('stores/test123', Session::get('context'));
        $this->assertEquals('test123', Session::get('store_hash'));
    }

    public function testUnknownUserGetsStoredWhenLoadingTheApp()
    {
        $this->bigCommerceServiceMock->method('verifySignedRequest')
            ->willReturn(
                [
                    'user' => [
                        'id' => 999999,
                        'email' => 'new-user@store.com'
                    ],
                    'owner' => [
                        'id' => $this->owner->id,
                        'email' => $this->owner->email
                    ],
                    'context' => $this->store->context,
                    'store_hash' => 'test123',
                    'timestamp' => time()
                ]
            );

        $request = Request::create('/auth/load', 'GET', [
            'signed_payload' => '123dummy'
        ]);
        $authController = new AuthController();
        $authController->load($request, $this->bigCommerceServiceMock);

        $user = User::where('bigcommerce_user_id', 999999)->first();
        $this->assertInstanceOf(User::class, $user);
        $this->assertEquals($this->owner->store_id, $user->store_id);
    }

    public function testReturnsForbiddenWhenUserIdIsNotOwnerId()
    {
        $this->setupDefaultBigCommerceServiceMock();
        $request = Request::create('/auth/uninstall', 'GET', [
            'signed_payload' => '123dummy'
        ]);

        $authController = new AuthController();
        $response = $authController->uninstall($request, $this->bigCommerceServiceMock);

        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame('Only store owners are allowed to uninstall an app', $response->getContent());
    }

    public function testUninstallActionRemovesAllUsersOfStore()
    {
        $this->bigCommerceServiceMock->method('verifySignedRequest')->willReturn([
            'user' => [
                'id' => $this->owner->bigcommerce_user_id,
                'username' => $this->owner->username,
                'email' => $this->owner->email,
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

        $request = Request::create('/auth/uninstall', 'GET', [
            'signed_payload' => '123dummy'
        ]);

        $authController = new AuthController();
        $authController->uninstall($request, $this->bigCommerceServiceMock);

        // Both owner and user should have been deleted
        $user = User::where('id', $this->user->id)->first();
        $owner = User::where('id', $this->owner->id)->first();
        $this->assertNull($user);
        $this->assertNull($owner);
    }

    private function setupDefaultBigCommerceServiceMock()
    {
        $this->bigCommerceServiceMock->method('verifySignedRequest')
            ->willReturn(
                [
                    'user' => [
                        'id' => 125688,
                        'email' => 'user@store.com'
                    ],
                    'owner' => [
                        'id' => 125689,
                        'email' => 'owner@store.com'
                    ],
                    'context' => 'stores/test123',
                    'store_hash' => 'test123',
                    'timestamp' => time()
                ]
            );
    }
}
