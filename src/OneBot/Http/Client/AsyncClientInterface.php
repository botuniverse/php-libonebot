<?php

declare(strict_types=1);

namespace OneBot\Http\Client;

use Psr\Http\Message\RequestInterface;

interface AsyncClientInterface
{
    /**
     * 以异步的形式发送 HTTP Request
     *
     * @param RequestInterface $request          请求对象
     * @param callable         $success_callback 成功请求的回调
     * @param callable         $error_callback   失败请求的回调
     */
    public function sendRequestAsync(RequestInterface $request, callable $success_callback, callable $error_callback);
}
