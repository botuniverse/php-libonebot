<?php

declare(strict_types=1);

namespace OneBot\Http;

use Http\Message\MessageFactory;
use Http\Message\StreamFactory;
use Http\Message\UriFactory;
use OneBot\Util\Singleton;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;

class HttpFactory implements MessageFactory, StreamFactory, UriFactory
{
    use Singleton;

    public function createRequest($method, $uri, array $headers = [], $body = null, $protocolVersion = '1.1'): RequestInterface
    {
        return new Request($method, $uri, $headers, $body, $protocolVersion);
    }

    public function createResponse($statusCode = 200, $reasonPhrase = null, array $headers = [], $body = null, $protocolVersion = '1.1'): ResponseInterface
    {
        return new Response((int) $statusCode, $headers, $body, $protocolVersion, $reasonPhrase);
    }

    public function createStream($body = null): StreamInterface
    {
        return Stream::create($body ?? '');
    }

    public function createUri($uri): UriInterface
    {
        if ($uri instanceof UriInterface) {
            return $uri;
        }
        return new Uri($uri);
    }
}
