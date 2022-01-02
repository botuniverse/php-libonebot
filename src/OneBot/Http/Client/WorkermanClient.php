<?php

declare(strict_types=1);

namespace OneBot\Http\Client;

use OneBot\Http\HttpFactory;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Workerman HTTP Client based on PSR-18.
 * TODO: 正在写
 */
class WorkermanClient implements ClientInterface
{
    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        return HttpFactory::getInstance()->createResponse();
    }
}
