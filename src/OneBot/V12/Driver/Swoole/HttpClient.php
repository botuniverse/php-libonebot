<?php

declare(strict_types=1);

namespace OneBot\V12\Driver\Swoole;

use OneBot\Http\HttpFactory;
use OneBot\Http\Response;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Swoole\Coroutine\Http\Client;
use function implode;

class HttpClient implements ClientInterface
{
    private $set = [];

    public function withSwooleSet(array $set): HttpClient
    {
        $this->set = $set;
        return $this;
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $client = $this->buildBaseClient($request);
        $uri = $request->getUri()->getPath();
        if (($query = $request->getUri()->getQuery()) !== '') {
            $uri .= '?' . $query;
        }
        if (($fragment = $request->getUri()->getFragment()) !== '') {
            $uri .= '?' . $fragment;
        }
        $result = $client->execute($uri);
        HttpFactory::getInstance()->createResponse();
        return new Response();
    }

    private function buildBaseClient(RequestInterface $request): Client
    {
        $uri = $request->getUri();
        $client = new Client($uri->getHost(), $uri->getPort() ?? ($uri->getScheme() === 'https' ? 443 : 80), $uri->getScheme() === 'https');
        // 设置 Swoole 专有的 set 参数
        $client->set($this->set);
        // 设置 HTTP Method （POST、GET 等）
        $client->setMethod($request->getMethod());
        // 设置 HTTP Headers
        $headers = [];
        foreach ($request->getHeaders() as $name => $values) {
            $headers[$name] = implode(', ', $values);
        }
        $client->setHeaders($headers);
        // 如果是 POST 带 body，则设置 body
        if (($data = $request->getBody()->getContents()) !== '') {
            $client->setData($data);
        }
        return $client;
    }
}
