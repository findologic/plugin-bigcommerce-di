<?php

use App\Http\Controllers\ConfigController;
use App\Models\Config;
use App\Models\Store;
use Illuminate\Http\Request;

class ConfigControllerTest extends TestCase
{
    public function testConfigurationIsSavedWithEmptyValues()
    {
        $request = Request::create('/auth/load', 'POST', [
            'store_hash' => 'test123',
            'access_token' => 'testAcessToken123',
            'context' => 'stores/test123',
            'shopkey' => '',
            'active_status' => ''
        ]);
        $configController = new ConfigController();
        $configController->handleConfiguration($request);

        $store = Store::where('domain', 'stores/test123')->first();
        $config = Config::where('store_id', $store['id'])->first();

        $this->assertFalse(boolval($config['active']));
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
        $configController = new ConfigController();
        $configController->handleConfiguration($request);

        $store = Store::where('domain', 'stores/test123')->first();
        $config = Config::where('store_id', $store['id'])->first();

        $this->assertTrue(boolval($config['active']));
        $this->assertEquals($store['id'], (int) $config['store_id']);
        $this->assertSame('123test', $config['shopkey']);
    }
}
