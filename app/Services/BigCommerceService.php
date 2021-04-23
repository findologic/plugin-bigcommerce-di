<?php

namespace App\Services;

use Exception;

class BigCommerceService
{
    public function verifySignedRequest(string $signedPayload): ?array
    {
        if (strpos($signedPayload, '.') !== false) {

            list($encodedData, $encodedSignature) = explode('.', $signedPayload, 2);

            $signature = base64_decode($encodedSignature);
            $jsonStr = base64_decode($encodedData);
            $data = json_decode($jsonStr, true);
            $this->validateSignature($signature, $jsonStr);

            if (!isset($data['owner']) || !isset($data['user']) || !isset($data['context'])) {
                throw new Exception('The signed request from BigCommerce has missing data!');
            }

            return $data;
        } else {
            return null;
        }
    }

    public function validateSignature($signature, $jsonStr) {
        $expectedSignature = hash_hmac('sha256', $jsonStr, env('CLIENT_SECRET'), $raw = false);
        if (!hash_equals($expectedSignature, $signature)) {
            throw new Exception('Bad signed request from BigCommerce!');
        }
    }
}
