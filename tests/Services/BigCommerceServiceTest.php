<?php

namespace Services;

use App\Services\BigCommerceService;
use Exception;
use PHPUnit\Framework\TestCase;

class BigCommerceServiceTest extends TestCase
{
    public function SignedPayloadDataProvider() {
        return [
            'User is missing' => [
                'signedPayload' => $this->getSignedPayload(
                    [
                        'owner' => $this->getDefaultUser(),
                        'context' => 'stores/test123',
                        'store_hash' => 'test123',
                        'timestamp' => time()
                    ]
            )],
            'Owner is missing' => [
                'signedPayload' =>
                    $this->getSignedPayload(
                    [
                        'user' => $this->getDefaultUser(),
                        'context' => 'stores/test123',
                        'store_hash' => 'test123',
                        'timestamp' => time()
                    ]
            )],
            'Context is missing' => [
                'signedPayload' => $this->getSignedPayload(
                    [
                        'user' => $this->getDefaultUser(),
                        'owner' => $this->getDefaultUser(),
                        'store_hash' => 'test123',
                        'timestamp' => time()
                    ]
            )],
        ];
    }

    /**
     * @dataProvider SignedPayloadDataProvider
     * @param string $signedPayload
     * @throws Exception
     */
    public function testExceptionIsThrownWhenVerifiedSignedRequestDataIsNotSet(string $signedPayload)
    {
        $this->expectException(Exception::class);
        $this->expectErrorMessage('The signed request from BigCommerce has missing data!');

        $bigCommerceServiceMock = $this->getMockBuilder(BigCommerceService::class)
            ->onlyMethods(['validateSignature'])
            ->getMock();
        $bigCommerceServiceMock->verifySignedRequest($signedPayload);
    }

    public function testExceptionIsThrownWhenPayloadCannotBeVerified()
    {
        $this->expectException(Exception::class);
        $this->expectErrorMessage('Bad signed request from BigCommerce!');

        $bigCommerceService = new BigCommerceService();
        $invalidSignedPayload = $this->getSignedPayload($this->getDefaultBigCommerceData());
        $bigCommerceService->verifySignedRequest($invalidSignedPayload);
    }

    private function getSignedPayload($data = array())
    {
        $data = json_encode($data ?? $this->getDefaultBigCommerceData());
        $signedPayload = base64_encode($data) . '.' . base64_encode('test-signature');

        return $signedPayload;
    }

    private function getDefaultBigCommerceData() {
       return [
            'user' => $this->getDefaultUser(),
            'owner' => $this->getDefaultUser(),
            'context' => 'stores/test123',
            'store_hash' => 'test123',
            'timestamp' => time()
        ];
    }

    private function getDefaultUser() {
        return [
            'id' => 125689,
            'email' => 'Johndoe55@gmail.com'
        ];
    }
}
