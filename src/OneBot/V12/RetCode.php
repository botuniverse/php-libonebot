<?php

declare(strict_types=1);

namespace OneBot\V12;

class RetCode
{
    public const OK = 0;

    public const BAD_REQUEST = 10001;

    public const UNSUPPORTED_ACTION = 10002;

    public const BAD_PARAM = 10003;

    public const UNSUPPORTED_PARAM = 10004;

    public const UNSUPPORTED_SEGMENT = 10005;

    public const BAD_SEGMENT_DATA = 10006;

    public const UNSUPPORTED_SEGMENT_DATA = 10007;

    public const BAD_HANDLER = 20001;

    public const INTERNAL_HANDLER_ERROR = 20002;

    public const DATABASE_ERROR = 31000;

    public const FILESYSTEM_ERROR = 32000;

    public const NETWORK_ERROR = 33000;

    public const PLATFORM_ERROR = 34000;

    public const LOGIC_ERROR = 35000;

    public const I_AM_TIRED = 36000;

    public const UNKNOWN_ERROR = 99999;

    public static function getMessage($retcode): string
    {
        $msg = [
            self::OK => 'OK',
            self::BAD_REQUEST => 'Bad Request',
            self::UNSUPPORTED_ACTION => 'Unsupported Action',
            self::BAD_PARAM => 'Invalid parameter',
            self::UNSUPPORTED_PARAM => 'Unsupported parameter',
            self::UNSUPPORTED_SEGMENT => 'Unsupported segment',
            self::BAD_SEGMENT_DATA => 'Bad segment data',
            self::UNSUPPORTED_SEGMENT_DATA => 'Unsupported segment data',
            self::BAD_HANDLER => 'Bad handler',
            self::INTERNAL_HANDLER_ERROR => 'Internal handler error',
            self::DATABASE_ERROR => 'Database error',
            self::FILESYSTEM_ERROR => 'Filesystem error',
            self::NETWORK_ERROR => 'Network error',
            self::PLATFORM_ERROR => 'Platform error',
            self::LOGIC_ERROR => 'Logic error',
            self::I_AM_TIRED => 'I am tired',
            self::UNKNOWN_ERROR => 'Unknown error',
        ];
        return $msg[$retcode] ?? 'Unknown error';
    }
}
