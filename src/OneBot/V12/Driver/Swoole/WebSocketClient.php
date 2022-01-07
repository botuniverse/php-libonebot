<?php

declare(strict_types=1);

namespace OneBot\V12\Driver\Swoole;

use OneBot\Http\Client\Exception\ClientException;
use OneBot\Http\Client\Exception\NetworkException;
use OneBot\Http\Client\SwooleClient;
use OneBot\Http\HttpFactory;
use OneBot\V12\Driver\WebSocketClientInterface;
use Psr\Http\Message\RequestInterface;
use Swoole\Coroutine\Http\Client;
use Swoole\WebSocket\Frame;

class WebSocketClient implements WebSocketClientInterface
{
    /** @var int */
    public $status = self::STATUS_INITIAL;

    private $set;

    /** @var Client */
    private $client;

    private $close_func;

    private $message_func;

    /**
     * @var RequestInterface
     */
    private $request;

    public function __construct(array $set = ['websocket_mask' => true])
    {
        $this->set = $set;
    }

    /**
     * @param  mixed           $address
     * @throws ClientException
     */
    public static function createFromAddress($address, array $header = [], array $set = ['websocket_mask' => true]): WebSocketClientInterface
    {
        return (new self($set))->withRequest(HttpFactory::getInstance()->createRequest('GET', $address, $header));
    }

    /**
     * @throws ClientException
     */
    public function withRequest(RequestInterface $request): WebSocketClientInterface
    {
        $this->request = $request;
        $this->client = (new SwooleClient($this->set))->buildBaseClient($request);
        return $this;
    }

    /**
     * @throws NetworkException
     */
    public function connect(): bool
    {
        if (!$this->status != self::STATUS_INITIAL) {
            return false;
        }
        ob_dump($this->request->getUri());
        $uri = $this->request->getUri()->getPath();
        if ($uri === '') {
            $uri = '/';
        }
        if (($query = $this->request->getUri()->getQuery()) !== '') {
            $uri .= '?' . $query;
        }
        if (($fragment = $this->request->getUri()->getFragment()) !== '') {
            $uri .= '?' . $fragment;
        }
        $r = $this->client->upgrade($uri);
        if ($this->client->errCode !== 0) {
            throw new NetworkException($this->request, $this->client->errMsg);
        }
        if ($r) {
            $this->status = self::STATUS_ESTABLISHED;
            go(function () {
                while (true) {
                    $result = $this->client->recv(60);
                    if ($result === false) {
                        if ($this->client->connected === false) {
                            $this->status = self::STATUS_CLOSED;
                            go(function () {
                                call_user_func($this->close_func, $this->client);
                            });
                            break;
                        }
                    } elseif ($result instanceof Frame) {
                        go(function () use ($result) {
                            call_user_func($this->message_func, $result, $this->client);
                        });
                    }
                }
            });
            return true;
        }
        return false;
    }

    public function setMessageCallback(callable $callable): WebSocketClientInterface
    {
        $this->message_func = $callable;
        return $this;
    }

    public function setCloseCallback(callable $callable): WebSocketClientInterface
    {
        $this->close_func = $callable;
        return $this;
    }

    public function send($data): bool
    {
        return $this->client->push($data);
    }
}
