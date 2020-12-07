<?php

use App\Http\Controllers\AuthController;
use App\Models\Config;
use App\Models\Store;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

class AuthControllerTest extends TestCase
{
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
        $parameters = [
            'code' => $code,
            'scope' => $scope,
            'context' => $context
        ];
        $request = Request::create('/auth/install', 'GET', array_filter($parameters));
        $authController = new AuthController();
        $response = $authController->install($request);

        $this->assertSame(400, $response->getStatusCode());
        $expectedMsg = 'Not enough information was passed to install this app';
        $this->assertSame($expectedMsg, $response->getContent());
    }

    public function testStoreIsSavedWhenInstallationIsSuccessful()
    {
        $mockedResponse = [
            'access_token' => 'test access token',
            'scope' => 'test scope',
            'context' => 'stores/test123',
            'user' => array(
                'id' => 125689,
                'username' => 'John Doe',
                'email' => 'Johndoe55@gmail.com'
            ),
        ];

        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application / json'], json_encode($mockedResponse))
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
        $response = $authController->install($request);

        $expectedMsg = 'Findologic App is successfully installed. Please refresh the page';
        $this->assertSame($expectedMsg, $response->getContent());

        $store = Store::where('domain', $mockedResponse['context'])->first();
        $this->assertSame($mockedResponse['context'], $store->domain);
        $this->assertSame($mockedResponse['access_token'], $store->access_token);
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
        $request = Request::create('/auth/load', 'GET', [
            'signed_payload' => '',
        ]);
        $authController = new AuthController();
        $response = $authController->load($request);

        $this->assertSame(400, $response->getStatusCode());
        $expectedMsg = 'The signed request from BigCommerce was empty';
        $this->assertSame($expectedMsg, $response->getContent());
    }

    public function testErrorAppearsWhenVerifiedSignedRequestDataIsNotSet()
    {
        $request = Request::create('/auth/load', 'GET', [
            'signed_payload' => '123dummy'
        ]);
        $authController = new AuthController();
        $response = $authController->load($request);

        $this->assertSame(400, $response->getStatusCode());
        $expectedMsg = 'The signed request from BigCommerce could not be validated';
        $this->assertSame($expectedMsg, $response->getContent());
    }

    public function testVerifiedSignedRequestDataIsSavedInSession()
    {
        $authControllerMock = $this->getMockBuilder(AuthController::class)->setMethodsExcept(['load'])->getMock();
        $authControllerMock->method('verifySignedRequest')
            ->willReturn(
                [
                    'user' => [
                        'id' => 125689,
                        'email' => 'Johndoe55@gmail.com'
                    ],
                    'owner' => [
                        'id' => 125689,
                        'email' => 'Johndoe55@gmail.com'
                    ],
                    'context' => 'stores/test123',
                    'store_hash' => 'test123',
                    'timestamp' => time()
                ]
            );

        $request = Request::create('/auth/load', 'GET', [
            'signed_payload' => '123dummy'
        ]);
        $authControllerMock->load($request);

        $this->assertEquals('test access token', Session::get('access_token'));
        $this->assertEquals('stores/test123', Session::get('context'));
        $this->assertEquals('test123', Session::get('store_hash'));
    }

    public function testConfigurationIsSavedWithEmptyValues()
    {
        $request = Request::create('/auth/load', 'POST', [
            'store_hash' => 'test123',
            'access_token' => 'testAcessToken123',
            'context' => 'stores/test123',
            'shopkey' => '',
            'active_status' => ''
        ]);
        $authController = new AuthController();
        $authController->handleConfiguration($request);

        $store = Store::where('domain', 'stores/test123')->first();
        $config = Config::where('store_id', $store['id'])->first();

        $this->assertFalse((bool) $config['active']);
        $this->assertEquals($store['id'], (int) $config['store_id']);
        $this->assertEmpty($config['shopkey']);
    }

    public function testConfigurationIsSavedWithValues()
    {
        $request = Request::create('/auth/load', 'POST', [
            'store_hash' => 'test123',
            'access_token' => 'testAcessToken123',
            'context' => 'stores/test123',
            'shopkey' => '123test',
            'active_status' => 'on'
        ]);
        $authController = new AuthController();
        $authController->handleConfiguration($request);

        $store = Store::where('domain', 'stores/test123')->first();
        $config = Config::where('store_id', $store['id'])->first();

        $this->assertTrue((bool) $config['active']);
        $this->assertEquals($store['id'], (int) $config['store_id']);
        $this->assertSame('123test', $config['shopkey']);
    }
}
