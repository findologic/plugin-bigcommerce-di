<?php

namespace Findologic\Tests\Traits;

trait MockResponseHelper
{
    protected function getMockResponse(string $path): string
    {
        return file_get_contents(__DIR__ . '/../MockData/' . $path);
    }
}
