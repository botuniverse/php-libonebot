<?php

declare(strict_types=1);

namespace OneBot\Http;

use InvalidArgumentException;
use OneBot\Util\Singleton;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;
use RuntimeException;

class HttpFactory
{
    use Singleton;

    /**
     * Creates a new PSR-7 request.
     *
     * @param string|UriInterface                  $uri
     * @param null|resource|StreamInterface|string $body
     * @param mixed                                $protocolVersion
     */
    public function createRequest(string $method, $uri, array $headers = [], $body = null, $protocolVersion = '1.1'): RequestInterface
    {
        return new Request($method, $uri, $headers, $body, $protocolVersion);
    }

    /**
     * Creates a new PSR-7 Server Request.
     * @param string|UriInterface                  $uri
     * @param null|resource|StreamInterface|string $body
     */
    public function createServerRequest(string $method, $uri, array $headers = [], $body = null, string $version = '1.1', array $serverParams = []): ServerRequestInterface
    {
        return new ServerRequest($method, $uri, $headers, $body, $version, $serverParams);
    }

    /**
     * @param mixed                                $statusCode
     * @param null|mixed                           $reasonPhrase
     * @param array                                $headers         Response headers
     * @param null|resource|StreamInterface|string $body            Response body
     * @param mixed                                $protocolVersion
     */
    public function createResponse($statusCode = 200, $reasonPhrase = null, array $headers = [], $body = null, $protocolVersion = '1.1'): ResponseInterface
    {
        return new Response((int) $statusCode, $headers, $body, $protocolVersion, $reasonPhrase);
    }

    /**
     * Creates a new PSR-7 stream.
     *
     * @param null|resource|StreamInterface|string $body
     *
     * @throws InvalidArgumentException if the stream body is invalid
     * @throws RuntimeException         if creating the stream from $body fails
     */
    public function createStream($body = null): StreamInterface
    {
        return Stream::create($body ?? '');
    }

    /**
     * Creates an PSR-7 URI.
     *
     * @param string|UriInterface $uri
     *
     * @throws InvalidArgumentException if the $uri argument can not be converted into a valid URI
     */
    public function createUri($uri): UriInterface
    {
        if ($uri instanceof UriInterface) {
            return $uri;
        }
        return new Uri($uri);
    }
}
