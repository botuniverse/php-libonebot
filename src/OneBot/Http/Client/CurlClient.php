<?php

/** @noinspection PhpComposerExtensionStubsInspection */

declare(strict_types=1);

namespace OneBot\Http\Client;

use OneBot\Http\Client\Exception\ClientException;
use OneBot\Http\Client\Exception\NetworkException;
use OneBot\Http\HttpFactory;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Curl HTTP Client based on PSR-18.
 * @see https://github.com/sunrise-php/http-client-curl/blob/master/src/Client.php
 */
class CurlClient implements ClientInterface
{
    protected $curl_options;

    public function __construct(array $curl_options = [])
    {
        $this->curl_options = $curl_options;
    }

    /**
     * {@inheritDoc}
     */
    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $handle = $this->createHandle($request);
        $success = curl_exec($handle);
        if ($success === false) {
            throw new NetworkException($request, curl_error($handle), curl_errno($handle));
        }
        $response = $this->createResponse($handle);
        curl_close($handle);
        return $response;
    }

    /**
     * @throws ClientException
     * @return resource
     */
    private function createHandle(RequestInterface $request)
    {
        $this->curl_options[CURLOPT_RETURNTRANSFER] = true; //返回的内容作为变量储存，而不是直接输出
        $this->curl_options[CURLOPT_HEADER] = true; //获取结果返回时包含Header数据
        $this->curl_options[CURLOPT_CUSTOMREQUEST] = $request->getMethod(); //设置请求方式
        $this->curl_options[CURLOPT_URL] = (string) $request->getUri(); //设置请求的URL
        $this->curl_options[CURLOPT_POSTFIELDS] = (string) $request->getBody(); //设置请求的Body
        //设置请求头
        foreach ($request->getHeaders() as $name => $values) {
            foreach ($values as $value) {
                $this->curl_options[CURLOPT_HTTPHEADER][] = sprintf('%s: %s', $name, $value);
            }
        }

        $curl_handle = curl_init();
        if ($curl_handle === false) {
            throw new ClientException('Unable to initialize a cURL handle');
        }

        $success = curl_setopt_array($curl_handle, $this->curl_options);
        if ($success === false) {
            throw new ClientException('Unable to configure a cURL handle');
        }

        return $curl_handle;
    }

    /**
     * @param resource $handle
     */
    private function createResponse($handle): ResponseInterface
    {
        $status_code = curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $response = HttpFactory::getInstance()->createResponse($status_code);

        $message = curl_multi_getcontent($handle);
        if ($message === null) {
            return $response;
        }

        $header_size = curl_getinfo($handle, CURLINFO_HEADER_SIZE);
        $header = substr($message, 0, $header_size);

        $fields = explode("\n", $header);
        foreach ($fields as $field) {
            $colpos = strpos($field, ':');
            if ($colpos === false) { // Status Line
                continue;
            }
            if ($colpos === 0) { // HTTP/2 Field
                continue;
            }

            [$name, $value] = explode(':', $field, 2);

            $response = $response->withAddedHeader(trim($name), trim($value));
        }

        $body = substr($message, $header_size);
        $response->getBody()->write($body);

        return $response;
    }
}
