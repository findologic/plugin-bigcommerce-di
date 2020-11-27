<?php

use App\Http\Controllers\AuthController;
use App\Models\Store;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Http\Request;

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

        $expectedMsg = 'Findologic App is successfully installed. Please refresh the page.';
        $this->assertSame($expectedMsg, $response->getContent());

        $store = Store::where('domain', $mockedResponse['context'])->first();
        $this->assertSame($mockedResponse['context'], $store->domain);
        $this->assertSame($mockedResponse['access_token'], $store->access_token);
    }

    public function testRequestExceptionOnInstall()
    {
        // check an error message appears when a RequestException happens.
        $this->get('/auth/install?code=cf1yzrfsrelwt6bh0k45oqm8sdung1e&context=stores%2Fj9ylyqeu8l&scope=store_content_checkout+store_sites+store_storefront_api+store_themes_manage+store_v2_content+store_v2_default+store_v2_information+users_basic_information');
        $this->assertEquals("<h4>Error:</h4> <p>Failed to retrieve access token from BigCommerce . Please try again .</p>", $this->response->getContent());
    }

    public function testSignedPayload()
    {
        // check an error message appears when signed_payload is not set.
        $this->get("/auth/load");
        $this->assertEquals("<h4>An issue has occurred:</h4> <p>The signed request from BigCommerce was empty.Please refresh the page some time internet issues.</p>", $this->response->getContent());
    }

    public function testVerifiedSignedRequestData()
    {
        // check an error message appears when verifiedSignedRequestData is not set.
        $this->get("/auth/load?signed_payload=123dummy");
        $this->assertEquals("<h4>An issue has occurred:</h4> <p>The signed request from BigCommerce could not be validated. Please refresh the page some time internet issues.</p>", $this->response->getContent());
    }

    public function testDataSavingOnLoad()
    {
        // check that data from verifiedSignedRequestData is put to Session.
        $this->get("/auth/load?signed_payload=123dummy&unitTest=1");
        $this->assertEquals("verifiedSignedRequestData is saved in session successfully", $this->response->getContent());
    }

    public function testConfigurationWithEmptyValues()
    {
        // check that data can be saved with empty values in DB
        $this->post("/config?emptyValues=1&unitTest=1", [
            "store_hash" => "test123",
            "access_token" => "testAcessToken123",
            "context" => "stores/test123",
            "shopkey" => ""
        ]);
        $this->assertEquals("Data saved in DB successfully with empty values", $this->response->getContent());
    }

    public function testConfigurationWithValues()
    {
        // check that data can be saved with values in DB
        $this->post("/config?values=1&unitTest=1", [
            "store_hash" => "test123",
            "access_token" => "testAcessToken123",
            "context" => "stores/test123",
            "shopkey" => "123test",
            "active_status" => "on"
        ]);
        $this->assertEquals("Data saved in DB successfully with values", $this->response->getContent());
    }
}
