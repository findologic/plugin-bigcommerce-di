<?php

namespace Controller;

use App\Http\Controllers\ConfigController;
use App\Models\Config;
use App\Models\Script;
use App\Models\Store;
use App\Services\BigCommerceService;
use App\Tests\Traits\MockResponseHelper;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Http\Request;
use TestCase;

class ConfigControllerTest extends TestCase
{
    use MockResponseHelper;

    private BigCommerceService $bigCommerceService;

    public function setUp(): void
    {
        $this->bigCommerceService = new BigCommerceService(new Client());

        parent::setUp();
        Script::query()->delete();
    }

    public function testConfigurationIsSavedWithEmptyValues()
    {
        $request = Request::create('/config', 'POST', [
            'store_hash' => 'test123',
            'access_token' => 'testAcessToken123',
            'context' => 'stores/test123',
            'shopkey' => '',
            'active_status' => ''
        ]);
        $configController = new ConfigController();
        $configController->handleConfiguration($request, $this->bigCommerceService);

        $store = Store::where('context', 'stores/test123')->first();
        $config = Config::where('store_id', $store['id'])->first();

        $this->assertFalse($config['active']);
        $this->assertEquals($store['id'], (int) $config['store_id']);
        $this->assertEmpty($config['shopkey']);
    }

    public function testHandleConfigurationSavesConfigAndScripts()
    {
        $mockedStoresXmlResponse = $this->getMockResponse('/xml_response/v2_stores.xml');
        $mockedScriptsJsonResponse = $this->getMockResponse('json_response/v3_content_scripts.json');

        $mock = new MockHandler([
            // REST-API call: stores/<store_hash>/v2/store
            new Response(200, ['Content-Type' => 'application/xml'], $mockedStoresXmlResponse),
            // REST-API call: stores/<store_hash>/v3/content/scripts
            new Response(200, ['Content-Type' => 'application/json'], $mockedScriptsJsonResponse),
        ]);
        $handler = HandlerStack::create($mock);
        $httpClientMock = new Client(['handler' => $handler]);
        $configController = new ConfigController($httpClientMock);

        $request = Request::create('/config', 'POST', [
            'store_hash' => 'test123',
            'access_token' => 'testAcessToken123',
            'context' => 'stores/test123',
            'shopkey' => '123test',
            'active_status' => 'on'
        ]);

        $bigCommerceService = new BigCommerceService($httpClientMock);
        $configController->handleConfiguration($request, $bigCommerceService);

        /** @var Store $store */
        $store = Store::where('context', 'stores/test123')->first();
        $config = Config::where('store_id', $store['id'])->first();

        $this->assertTrue($config['active']);
        $this->assertEquals($store['id'], (int) $config['store_id']);
        $this->assertSame('123test', $config['shopkey']);
        $this->assertDefaultScriptValues($store);
    }

    public function testConfigurationDoesNotSaveScriptWhenActiveIsNotSet()
    {
        $mockedStoresXmlResponse = $this->getMockResponse('/xml_response/v2_stores.xml');

        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/xml'], $mockedStoresXmlResponse),
        ]);
        $handler = HandlerStack::create($mock);
        $httpClientMock = new Client(['handler' => $handler]);
        $configController = new ConfigController($httpClientMock);

        $request = Request::create('/config', 'POST', [
            'store_hash' => 'test123',
            'access_token' => 'testAcessToken123',
            'context' => 'stores/test123',
            'shopkey' => '123test',
            'active_status' => null
        ]);
        $configController->handleConfiguration($request, $this->bigCommerceService);

        /** @var Store $store */
        $store = Store::where('context', 'stores/test123')->first();
        $config = Config::where('store_id', $store['id'])->first();

        $this->assertFalse($config['active']);
        $this->assertEquals($store['id'], (int) $config['store_id']);
        $this->assertSame('123test', $config['shopkey']);

        $this->assertEquals(0, $store->scripts->count());
    }

    public function testSavingNewConfigWillUpdateTheScriptWithNewConfiguration()
    {
        $existingStore = new Store([
            'context' => 'stores/test-shop',
            'access_token' => 'test-token'
        ]);
        $existingStore->save();

        $existingScript = new Script([
            'name' => 'Findologic',
            'uuid' => 'test-uuid',
            'store_hash' => 'test123',
            'store_id' => $existingStore->id
        ]);
        $existingScript->save();

        $mockedStoresXmlResponse = $this->getMockResponse('/xml_response/v2_stores.xml');
        $mockedScriptsJsonResponse = $this->getMockResponse('json_response/v3_content_scripts.json');

        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/xml'], $mockedStoresXmlResponse),
            new Response(204), // Script delete call stores/<store_hash>/v3/content/scripts/<uuid>
            new Response(200, ['Content-Type' => 'application/json'], $mockedScriptsJsonResponse),
        ]);
        $handler = HandlerStack::create($mock);
        $httpClientMock = new Client(['handler' => $handler]);
        $configController = new ConfigController($httpClientMock);

        $request = Request::create('/config', 'POST', [
            'store_hash' => 'test123',
            'access_token' => 'testAcessToken123',
            'context' => 'stores/test123',
            'shopkey' => 'UPDATED_SHOPKEY',
            'active_status' => 'on'
        ]);

        $bigCommerceService = new BigCommerceService($httpClientMock);
        $configController->handleConfiguration($request, $bigCommerceService);

        /** @var Store $store */
        $store = Store::where('context', 'stores/test123')->first();
        $config = Config::where('store_id', $store['id'])->first();

        $this->assertTrue($config['active']);
        $this->assertEquals($store['id'], (int) $config['store_id']);
        $this->assertSame('UPDATED_SHOPKEY', $config['shopkey']);
        $this->assertDefaultScriptValues($store);
    }

    private function assertDefaultScriptValues(Store $store)
    {
        $this->assertEquals(1, $store->scripts->count());
        $script = $store->scripts()->first();
        $this->assertEquals('Findologic', $script->name);
        $this->assertEquals('9dd04fae-d45d-5a64-a45d-62d14d2c62b5', $script->uuid);
        $this->assertStringContainsString($script->store_hash, $store->context);
        $this->assertEquals($store->id, $script->store_id);
    }
}
