<?php

declare(strict_types=1);

namespace OneBot\Http\Client\Exception;

use OneBot\V12\Exception\OneBotException;
use Psr\Http\Client\RequestExceptionInterface;
use Psr\Http\Message\RequestInterface;
use Throwable;

class RequestException extends OneBotException implements RequestExceptionInterface
{
    private $request;

    public function __construct(RequestInterface $request, $message = '', $code = 0, Throwable $previous = null)
    {
        $this->request = $request;
        parent::__construct($message, $code, $previous);
    }

    /**
     * {@inheritDoc}
     */
    public function getRequest(): RequestInterface
    {
        return $this->request;
    }
}
