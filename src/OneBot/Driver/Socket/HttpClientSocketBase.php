<?php

declare(strict_types=1);

namespace OneBot\Driver\Socket;

use Choir\Http\Client\AsyncClientInterface;
use Choir\Http\Client\Exception\ClientException;
use Choir\Http\HttpFactory;
use OneBot\Driver\Driver;
use OneBot\Driver\Interfaces\SocketInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;

abstract class HttpClientSocketBase implements SocketInterface
{
    use SocketFlag;
    use SocketConfig;

    protected $url;

    protected $headers;

    protected $access_token;

    protected $timeout;

    /**
     * @var AsyncClientInterface|ClientInterface
     */
    private $client_cache;

    private bool $no_async = false;

    private bool $client_cache_async = false;

    public function __construct(array $config)
    {
        $this->url = $config['url'];
        $this->headers = $config['headers'] ?? [];
        $this->access_token = $config['access_token'] ?? '';
        $this->timeout = $config['timeout'] ?? 5;
        $this->config = $config;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function getAccessToken(): string
    {
        return $this->access_token;
    }

    public function getTimeout(): int
    {
        return $this->timeout;
    }

    public function withoutAsync(bool $no_async = true): HttpClientSocketBase
    {
        $this->no_async = $no_async;
        return $this;
    }

    public function get(array $headers, callable $success_callback, callable $error_callback)
    {
        $request = HttpFactory::createRequest('GET', $this->url, array_merge($this->headers, $headers));
        return $this->sendRequest($request, $success_callback, $error_callback);
    }

    /**
     * @param  array|\JsonSerializable|string $data             数据
     * @param  array                          $headers          头
     * @param  callable                       $success_callback 成功回调
     * @param  callable                       $error_callback   错误回调
     * @return bool|mixed
     */
    public function post($data, array $headers, callable $success_callback, callable $error_callback)
    {
        if ($data instanceof \JsonSerializable) {
            $data = json_encode($data);
        }
        $request = HttpFactory::createRequest('POST', $this->url, array_merge($this->headers, $headers), $data);
        return $this->sendRequest($request, $success_callback, $error_callback);
    }

    /**
     * @param  RequestInterface $request 请求对象
     * @return bool|mixed
     */
    public function sendRequest(RequestInterface $request, callable $success_callback, callable $error_callback)
    {
        if ($this->client_cache === null) {
            $class = Driver::getActiveDriverClass();
            foreach (($class::SUPPORTED_CLIENTS ?? []) as $v) {
                if (is_a($v, AsyncClientInterface::class, true)) {
                    $this->client_cache_async = true;
                }
                try {
                    /* @throws ClientException */
                    $this->client_cache = new $v();
                    $this->client_cache->setTimeout($this->timeout * 1000);
                } catch (ClientException $e) {
                    continue;
                }
                break;
            }
        }
        if ($this->client_cache_async && !$this->no_async) {
            $this->client_cache->sendRequestAsync($request, $success_callback, $error_callback);
            return true;
        }
        try {
            $response = $this->client_cache->sendRequest($request);
            return $success_callback($response);
        } catch (\Throwable $e) {
            return $error_callback($request, $e);
        }
    }
}
