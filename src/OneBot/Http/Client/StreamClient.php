<?php

declare(strict_types=1);

namespace OneBot\Http\Client;

use Exception;
use OneBot\Http\Client\Exception\NetworkException;
use OneBot\Http\Client\Exception\RequestException;
use OneBot\Http\Response;
use OneBot\Http\Stream;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Stream HTTP Client based on PSR-18.
 * @see https://github.com/php-http/socket-client
 */
class StreamClient implements ClientInterface
{
    private $config = [
        'remote_socket' => null,
        'timeout' => 1000, // 单位：毫秒
        'stream_context_options' => [],
        'stream_context_param' => [],
        'ssl' => null,
        'write_buffer_size' => 8192,
        'ssl_method' => STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT,
    ];

    /**
     * Constructor.
     *
     * @param mixed $config
     */
    public function __construct($config = [])
    {
        $this->config = empty($config) ? $this->config : $config;
        $this->config['stream_context'] = stream_context_create($this->config['stream_context_options'], $this->config['stream_context_param']);
    }

    /**
     * {@inheritdoc}
     * @throws Exception
     */
    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $remote = $this->config['remote_socket'];
        $useSsl = $this->config['ssl'];

        if (!$request->hasHeader('Connection')) {
            $request = $request->withHeader('Connection', 'close');
        }

        if ($remote === null) {
            $remote = $this->determineRemoteFromRequest($request);
        }

        if ($useSsl === null) {
            $useSsl = ($request->getUri()->getScheme() === 'https');
        }

        $socket = $this->createSocket($request, $remote, $useSsl);

        try {
            $this->writeRequest($socket, $request, $this->config['write_buffer_size']);
            $response = $this->readResponse($request, $socket);
        } catch (Exception $e) {
            $this->closeSocket($socket);
            throw $e;
        }

        return $response;
    }

    /**
     * Create the socket to write request and read response on it.
     *
     * @param RequestInterface $request Request for
     * @param string           $remote  Entrypoint for the connection
     * @param bool             $useSsl  Whether to use ssl or not
     *
     * @throws NetworkException
     * @return resource         Socket resource
     */
    protected function createSocket(RequestInterface $request, string $remote, bool $useSsl)
    {
        $errNo = null;
        $errMsg = null;
        $socket = @stream_socket_client($remote, $errNo, $errMsg, floor($this->config['timeout'] / 1000), STREAM_CLIENT_CONNECT, $this->config['stream_context']);

        if ($socket === false) {
            if ($errNo === 110) {
                throw new NetworkException($request, $errMsg);
            }

            throw new NetworkException($request, $errMsg);
        }

        stream_set_timeout($socket, (int) floor($this->config['timeout'] / 1000), $this->config['timeout'] % 1000);

        if ($useSsl && @stream_socket_enable_crypto($socket, true, $this->config['ssl_method']) === false) {
            throw new NetworkException($request, sprintf('Cannot enable tls: %s', error_get_last()['message']));
        }

        return $socket;
    }

    /**
     * Close the socket, used when having an error.
     *
     * @param resource $socket
     */
    protected function closeSocket($socket): void
    {
        fclose($socket);
    }

    /**
     * Write a request to a socket.
     *
     * @param  resource         $socket
     * @throws NetworkException
     */
    protected function writeRequest($socket, RequestInterface $request, int $bufferSize = 8192): void
    {
        if ($this->fwrite($socket, $this->transformRequestHeadersToString($request)) === false) {
            throw new NetworkException($request, 'Failed to send request, underlying socket not accessible, (BROKEN EPIPE)');
        }

        if ($request->getBody()->isReadable()) {
            $this->writeBody($socket, $request, $bufferSize);
        }
    }

    /**
     * Write Body of the request.
     *
     * @param  resource         $socket
     * @throws NetworkException
     */
    protected function writeBody($socket, RequestInterface $request, int $bufferSize = 8192): void
    {
        $body = $request->getBody();

        if ($body->isSeekable()) {
            $body->rewind();
        }

        while (!$body->eof()) {
            $buffer = $body->read($bufferSize);

            if ($this->fwrite($socket, $buffer) === false) {
                throw new NetworkException($request, 'An error occur when writing request to client (BROKEN EPIPE)');
            }
        }
    }

    /**
     * Produce the header of request as a string based on a PSR Request.
     */
    protected function transformRequestHeadersToString(RequestInterface $request): string
    {
        $message = vsprintf('%s %s HTTP/%s', [
            strtoupper($request->getMethod()),
            $request->getRequestTarget(),
            $request->getProtocolVersion(),
        ]) . "\r\n";

        foreach ($request->getHeaders() as $name => $values) {
            $message .= $name . ': ' . implode(', ', $values) . "\r\n";
        }

        $message .= "\r\n";

        return $message;
    }

    /**
     * Read a response from a socket.
     *
     * @param resource $socket
     *
     * @throws NetworkException
     */
    protected function readResponse(RequestInterface $request, $socket): ResponseInterface
    {
        $headers = [];
        $reason = null;

        while (false !== ($line = fgets($socket))) {
            if (rtrim($line) === '') {
                break;
            }
            $headers[] = trim($line);
        }

        $metadatas = stream_get_meta_data($socket);

        if (array_key_exists('timed_out', $metadatas) && $metadatas['timed_out'] === true) {
            throw new NetworkException($request, 'Error while reading response, stream timed out');
        }

        $parts = explode(' ', array_shift($headers), 3);

        if (count($parts) <= 1) {
            throw new NetworkException($request, 'Cannot read the response');
        }

        $protocol = substr($parts[0], -3);
        $status = $parts[1];

        if (isset($parts[2])) {
            $reason = $parts[2];
        }

        // Set the size on the stream if it was returned in the response
        $responseHeaders = [];

        foreach ($headers as $header) {
            $headerParts = explode(':', $header, 2);

            if (!array_key_exists(trim($headerParts[0]), $responseHeaders)) {
                $responseHeaders[trim($headerParts[0])] = [];
            }

            $responseHeaders[trim($headerParts[0])][] = isset($headerParts[1])
                ? trim($headerParts[1])
                : '';
        }

        $response = new Response($status, $responseHeaders, null, $protocol, $reason);
        $stream = Stream::create($socket);

        return $response->withBody($stream);
    }

    /**
     * Return remote socket from the request.
     *
     * @throws RequestException
     */
    private function determineRemoteFromRequest(RequestInterface $request): string
    {
        if (!$request->hasHeader('Host') && $request->getUri()->getHost() === '') {
            throw new RequestException($request, 'Remote is not defined and we cannot determine a connection endpoint for this request (no Host header)');
        }

        $endpoint = '';

        $host = $request->getUri()->getHost();
        if (!empty($host)) {
            $endpoint .= $host;
            if ($request->getUri()->getPort() !== null) {
                $endpoint .= ':' . $request->getUri()->getPort();
            } elseif ($request->getUri()->getScheme() === 'https') {
                $endpoint .= ':443';
            } else {
                $endpoint .= ':80';
            }
        }

        // If use the host header if present for the endpoint
        if (empty($host) && $request->hasHeader('Host')) {
            $endpoint = $request->getHeaderLine('Host');
        }

        return sprintf('tcp://%s', $endpoint);
    }

    /**
     * Replace fwrite behavior as api is broken in PHP.
     *
     * @see https://secure.phabricator.com/rPHU69490c53c9c2ef2002bc2dd4cecfe9a4b080b497
     *
     * @param resource $stream The stream resource
     *
     * @return bool|int false if pipe is broken, number of bytes written otherwise
     */
    private function fwrite($stream, string $bytes)
    {
        if (empty($bytes)) {
            return 0;
        }
        $result = @fwrite($stream, $bytes);
        if ($result !== 0) {
            // In cases where some bytes are witten (`$result > 0`) or
            // an error occurs (`$result === false`), the behavior of fwrite() is
            // correct. We can return the value as-is.
            return $result;
        }
        // If we make it here, we performed a 0-length write. Try to distinguish
        // between EAGAIN and EPIPE. To do this, we're going to `stream_select()`
        // the stream, write to it again if PHP claims that it's writable, and
        // consider the pipe broken if the write fails.
        $read = [];
        $write = [$stream];
        $except = [];
        $ss = @stream_select($read, $write, $except, 0);
        // 这里做了个修改，原来下面是 !$write，但静态分析出来它是永久的false，所以改成了 !$ss
        if (!$ss) {
            // The stream isn't writable, so we conclude that it probably really is
            // blocked and the underlying error was EAGAIN. Return 0 to indicate that
            // no data could be written yet.
            return 0;
        }
        // If we make it here, PHP **just** claimed that this stream is writable, so
        // perform a write. If the write also fails, conclude that these failures are
        // EPIPE or some other permanent failure.
        $result = @fwrite($stream, $bytes);
        if ($result !== 0) {
            // The write worked or failed explicitly. This value is fine to return.
            return $result;
        }
        // We performed a 0-length write, were told that the stream was writable, and
        // then immediately performed another 0-length write. Conclude that the pipe
        // is broken and return `false`.
        return false;
    }
}
