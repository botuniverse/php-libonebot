<?php

declare(strict_types=1);

namespace OneBot\Http\Client\Exception;

use OneBot\V12\Exception\OneBotException;
use Psr\Http\Client\ClientExceptionInterface;

class ClientException extends OneBotException implements ClientExceptionInterface
{
}
