<?php

declare(strict_types=1);

namespace OneBot\Driver\Socket;

use JsonSerializable;
use OneBot\Driver\Driver;
use OneBot\Driver\Interfaces\SocketInterface;
use OneBot\Http\Client\AsyncClientInterface;
use OneBot\Http\Client\ClientBase;
use OneBot\Http\Client\Exception\ClientException;
use OneBot\Http\HttpFactory;
use Psr\Http\Client\ClientInterface;
use Throwable;

abstract class HttpClientSocketBase implements SocketInterface
{
    use SocketFlag;

    protected $url;

    protected $headers;

    protected $access_token;

    protected $timeout;

    /**
     * @var AsyncClientInterface|ClientBase|ClientInterface
     */
    private $client_cache;

    private $client_cache_async = false;

    public function __construct(string $url, array $headers = [], string $access_token = '', int $timeout = 5)
    {
        $this->url = $url;
        $this->headers = $headers;
        $this->access_token = $access_token;
        $this->timeout = $timeout;
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

    public function post($data, array $headers, callable $success_callback, callable $error_callback)
    {
        if ($data instanceof JsonSerializable) {
            $data = json_encode($data);
        }
        if ($this->client_cache === null) {
            $class = Driver::getActiveDriverClass();
            foreach (($class::SUPPORTED_CLIENTS ?? []) as $v) {
                if (is_a($v, AsyncClientInterface::class, true)) {
                    $this->client_cache_async = true;
                }
                try {
                    /* @throws ClientException */
                    $this->client_cache = new $v();
                    $this->client_cache->setTimeout($this->timeout);
                } catch (ClientException $e) {
                    continue;
                }
                break;
            }
        }
        $request = HttpFactory::getInstance()->createRequest('POST', $this->url, array_merge($this->headers, $headers), $data);
        if ($this->client_cache_async) {
            $this->client_cache->sendRequestAsync($request, $success_callback, $error_callback);
        } else {
            try {
                $response = $this->client_cache->sendRequest($request);
                $success_callback($response);
            } catch (Throwable $e) {
                $error_callback($request);
            }
        }
    }
}
