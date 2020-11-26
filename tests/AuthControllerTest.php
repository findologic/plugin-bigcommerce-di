<?php

use Laravel\Lumen\Testing\DatabaseMigrations;
use Laravel\Lumen\Testing\DatabaseTransactions;

class AuthControllerTest extends TestCase
{
    /**
     * A basic test example.
     *
     * @return void
     */
    public function testRequestParamOnInstall()
    {
        // check an error message appears when code, scope, or context param is not provided.
        $this->get('/auth/install');
        $this->assertEquals("<h4>An issue has occurred:</h4> <p>Not enough information was passed to install this app. Please refresh the page some time internet issues.</p> ",$this->response->getContent());
    }
    public function testDataSavingOnInstall()
    {
        // check data from authentication is put to Session / db after successful response.
        $this->get('/auth/install?code=cf1yzrfsrelwt6bh0k45oqm8sdung1e&context=stores%2Fj9ylyqeu8l&scope=store_content_checkout+store_sites+store_storefront_api+store_themes_manage+store_v2_content+store_v2_default+store_v2_information+users_basic_information&unitTest=1');
        $this->assertEquals("<h3>Findologic App is successfully installed . Please refresh the page.</h3>",$this->response->getContent());
    }
    public function testRequestExceptionOnInstall()
    {
        // check an error message appears when a RequestException happens.
        $this->get('/auth/install?code=cf1yzrfsrelwt6bh0k45oqm8sdung1e&context=stores%2Fj9ylyqeu8l&scope=store_content_checkout+store_sites+store_storefront_api+store_themes_manage+store_v2_content+store_v2_default+store_v2_information+users_basic_information');
        $this->assertEquals("<h4>Error:</h4> <p>Failed to retrieve access token from BigCommerce . Please try again .</p>",$this->response->getContent());
    }

    public function testSignedPayload()
    {
       // check an error message appears when signed_payload is not set.
        $this->get("/auth/load");
        $this->assertEquals("<h4>An issue has occurred:</h4> <p>The signed request from BigCommerce was empty.Please refresh the page some time internet issues.</p>",$this->response->getContent());
    }

    public function testVerifiedSignedRequestData()
    {
        // check an error message appears when verifiedSignedRequestData is not set.
        $this->get("/auth/load?signed_payload=123dummy");
        $this->assertEquals("<h4>An issue has occurred:</h4> <p>The signed request from BigCommerce could not be validated. Please refresh the page some time internet issues.</p>",$this->response->getContent());
    }

    public function testDataSavingOnLoad()
    {
        // check that data from verifiedSignedRequestData is put to Session.
        $this->get("/auth/load?signed_payload=123dummy&unitTest=1");
        $this->assertEquals("verifiedSignedRequestData is saved in session successfully",$this->response->getContent());
    }

    public function testConfigurationWithEmptyValues()
    {
        // check that data can be saved with empty values in DB
        $this->post("/config?emptyValues=1&unitTest=1",[
            "store_hash" => "test123",
            "access_token" => "testAcessToken123",
            "context" => "stores/test123",
            "shopkey" => ""
        ]);
        $this->assertEquals("Data saved in DB successfully with empty values",$this->response->getContent());
    }

    public function testConfigurationWithValues()
    {
        // check that data can be saved with values in DB
        $this->post("/config?values=1&unitTest=1",[
            "store_hash" => "test123",
            "access_token" => "testAcessToken123",
            "context" => "stores/test123",
            "shopkey" => "123test",
            "active_status" => "on"
        ]);
        $this->assertEquals("Data saved in DB successfully with values",$this->response->getContent());
    }

}
