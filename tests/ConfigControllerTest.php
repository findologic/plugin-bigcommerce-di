<?php

use Findologic\Http\Controllers\ConfigController;
use Findologic\Models\Config;
use Findologic\Models\Script;
use Findologic\Models\Store;
use Findologic\Tests\Traits\MockResponseHelper;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Http\Request;

class ConfigControllerTest extends TestCase
{
    use MockResponseHelper;

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
        $configController->handleConfiguration($request);

        $store = Store::where('context', 'stores/test123')->first();
        $config = Config::where('store_id', $store['id'])->first();

        $this->assertFalse(boolval($config['active']));
        $this->assertEquals($store['id'], (int) $config['store_id']);
        $this->assertEmpty($config['shopkey']);
    }

    public function testHandleConfigurationSavesConfigAndScripts()
    {
        Script::query()->delete();

        $mockedStoresXmlResponse = $this->getMockResponse('/xml_response/v2_stores.xml');
        $mockedScriptsJsonResponse = $this->getMockResponse('json_response/v3_content_scripts.json');

        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/xml'], $mockedStoresXmlResponse),
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
        $configController->handleConfiguration($request);

        /** @var Store $store */
        $store = Store::where('context', 'stores/test123')->first();
        $config = Config::where('store_id', $store['id'])->first();

        $this->assertTrue(boolval($config['active']));
        $this->assertEquals($store['id'], (int) $config['store_id']);
        $this->assertSame('123test', $config['shopkey']);

        $this->assertEquals(1, $store->scripts->count());
        $script = $store->scripts()->first();

        $this->assertEquals('Findologic', $script->name);
        $this->assertEquals('9dd04fae-d45d-5a64-a45d-62d14d2c62b5', $script->uuid);
        $this->assertEquals(str_replace('stores/', '', $store->context), $script->store_hash);
        $this->assertEquals($store->id, $script->store_id);
    }
}
