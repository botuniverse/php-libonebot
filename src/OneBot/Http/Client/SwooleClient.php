<?php

declare(strict_types=1);

namespace OneBot\Http\Client;

use OneBot\Http\Client\Exception\ClientException;
use OneBot\Http\Client\Exception\NetworkException;
use OneBot\Http\HttpFactory;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Swoole\Coroutine;
use Swoole\Coroutine\Http\Client;

/**
 * Swoole HTTP Client based on PSR-18.
 */
class SwooleClient extends ClientBase implements ClientInterface, AsyncClientInterface
{
    private $set = [];

    /**
     * @throws ClientException
     */
    public function __construct(array $set = [])
    {
        if (Coroutine::getCid() === -1) {
            throw new ClientException('API must be called in the coroutine');
        }
        $this->withSwooleSet($set);
    }

    public function withSwooleSet(array $set = []): SwooleClient
    {
        if (!empty($set)) {
            $this->set = $set;
        }
        return $this;
    }

    public function setTimeout(int $timeout)
    {
        $this->set['timeout'] = $timeout / 1000;
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $client = $this->buildBaseClient($request);
        if ($client->errCode !== 0) {
            throw new NetworkException($request, $client->errMsg, $client->errCode);
        }
        return HttpFactory::getInstance()->createResponse($client->statusCode, null, $client->getHeaders(), $client->getBody());
    }

    public function sendRequestAsync(RequestInterface $request, callable $success_callback, callable $error_callback)
    {
        go(function () use ($request, $success_callback, $error_callback) {
            $client = $this->buildBaseClient($request);
            if ($client->errCode !== 0) {
                call_user_func($error_callback, $request);
            } else {
                $response = HttpFactory::getInstance()->createResponse($client->statusCode, null, $client->getHeaders(), $client->getBody());
                call_user_func($success_callback, $response);
            }
        });
    }

    public function buildBaseClient(RequestInterface $request): Client
    {
        $uri = $request->getUri();
        $client = new Client($uri->getHost(), $uri->getPort() ?? ($uri->getScheme() === 'https' ? 443 : 80), $uri->getScheme() === 'https');
        // ?????? Swoole ????????? set ??????
        $client->set($this->set);
        // ?????? HTTP Method ???POST???GET ??????
        $client->setMethod($request->getMethod());
        // ?????? HTTP Headers
        $headers = [];
        foreach ($request->getHeaders() as $name => $values) {
            $headers[$name] = implode(', ', $values);
        }
        $client->setHeaders($headers);
        // ????????? POST ??? body???????????? body
        if (($data = $request->getBody()->getContents()) !== '') {
            $client->setData($data);
        }
        $uri = $request->getUri()->getPath();
        if ($uri === '') {
            $uri = '/';
        }
        if (($query = $request->getUri()->getQuery()) !== '') {
            $uri .= '?' . $query;
        }
        if (($fragment = $request->getUri()->getFragment()) !== '') {
            $uri .= '?' . $fragment;
        }
        $client->execute($uri);
        return $client;
    }
}
