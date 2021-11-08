<?php

namespace OneBot\V12;

class RetCode
{
    const OK = 0;

    const BAD_REQUEST = 10001;
    const UNSUPPORTED_ACTION = 10002;
    const BAD_PARAM = 10003;
    const UNSUPPORTED_PARAM = 10004;
    const UNSUPPORTED_SEGMENT = 10005;
    const BAD_SEGMENT_DATA = 10006;
    const UNSUPPORTED_SEGMENT_DATA = 10007;

    const BAD_HANDLER = 20001;
    const INTERNAL_HANDLER_ERROR = 20002;

    const DATABASE_ERROR = 31000;
    const FILESYSTEM_ERROR = 32000;
    const NETWORK_ERROR = 33000;
    const PLATFORM_ERROR = 34000;
    const LOGIC_ERROR = 35000;
    const I_AM_TIRED = 36000;

    const UNKNOWN_ERROR = 99999;

    public static function getMessage($retcode): string {
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