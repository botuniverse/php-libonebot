<?php

declare(strict_types=1);

namespace OneBot\Logger\Console;

use OneBot\Util\Singleton;
use Psr\Log\AbstractLogger;
use Psr\Log\InvalidArgumentException;
use Psr\Log\LogLevel;

class ConsoleLogger extends AbstractLogger
{
    use Singleton;

    public static $format = '[%date%] [%level%] %body%';

    public static $date_format = 'Y-m-d H:i:s';

    /**
     * @var string[] 颜色表
     *
     * TODO: redesign color schema
     */
    protected static $colors = [
        LogLevel::EMERGENCY => 'red',
        LogLevel::ALERT => 'red',
        LogLevel::CRITICAL => 'red',
        LogLevel::ERROR => 'red',
        LogLevel::WARNING => 'yellow',
        LogLevel::NOTICE => 'yellow',
        LogLevel::INFO => 'lightblue',
        LogLevel::DEBUG => 'gray',
    ];

    protected static $levels = [
        LogLevel::EMERGENCY, // 0
        LogLevel::ALERT, // 1
        LogLevel::CRITICAL, // 2
        LogLevel::ERROR, // 3
        LogLevel::WARNING, // 4
        LogLevel::NOTICE, // 5
        LogLevel::INFO, // 6
        LogLevel::DEBUG, // 7
    ];

    protected static $logLevel;

    private function __construct($logLevel = LogLevel::INFO)
    {
        self::$logLevel = $logLevel;
    }

    public function colorize($string, $level)
    {
        $string = $this->stringify($string);
        $color = self::$colors[$level] ?? '';
        switch ($color) {
            case 'black':
                return TermColor::color8(30) . $string . TermColor::RESET;
            case 'red':
                return TermColor::color8(31) . $string . TermColor::RESET;
            case 'green':
                return TermColor::color8(32) . $string . TermColor::RESET;
            case 'yellow':
                return TermColor::color8(33) . $string . TermColor::RESET;
            case 'blue':
                return TermColor::color8(34) . $string . TermColor::RESET;
            case 'pink': // I really don't know what stupid color it is.
            case 'lightpurple':
                return TermColor::color8(35) . $string . TermColor::RESET;
            case 'lightblue':
                return TermColor::color8(36) . $string . TermColor::RESET;
            case 'white':
                return TermColor::color8(37) . $string . TermColor::RESET;
            case 'gold':
                return TermColor::frontColor256(214) . $string . TermColor::RESET;
            case 'gray':
                return TermColor::frontColor256(59) . $string . TermColor::RESET;
            case 'lightlightblue':
                return TermColor::frontColor256(63) . $string . TermColor::RESET;
            case '':
                return $string;
            default:
                return TermColor::frontColor256($color) . $string . TermColor::RESET;
        }
    }

    public function trace()
    {
        $log = "Stack trace:\n";
        $trace = debug_backtrace();
        //array_shift($trace);
        foreach ($trace as $i => $t) {
            if (!isset($t['file'])) {
                $t['file'] = 'unknown';
            }
            if (!isset($t['line'])) {
                $t['line'] = 0;
            }
            if (!isset($t['function'])) {
                $t['function'] = 'unknown';
            }
            $log .= "#{$i} {$t['file']}({$t['line']}): ";
            if (isset($t['object']) and is_object($t['object'])) {
                $log .= get_class($t['object']) . '->';
            }
            $log .= "{$t['function']}()\n";
        }
        $log = $this->colorize($log, LogLevel::DEBUG);
        echo $log;
    }

    public function getTrace(): string
    {
//        if (self::$info_level !== null && self::$info_level == 4) {
//            $trace = debug_backtrace()[2] ?? ['file' => '', 'function' => ''];
//            $trace = '[' . ($trace['class'] ?? '') . ':' . ($trace['function'] ?? '') . '] ';
//        }
//        return $trace ?? '';
        return '';
    }

//        $trace = debug_backtrace()[1] ?? ['file' => '', 'function' => ''];
//        $trace = '[' . ($trace['class'] ?? '') . ':' . ($trace['function'] ?? '') . '] ';

    public function log($level, $message, array $context = [])
    {
        if (!in_array($level, self::$levels)) {
            throw new InvalidArgumentException();
        }

        if (array_flip(self::$levels)[$level] > self::$logLevel) {
            return;
        }

        $output = str_replace(
            ['%date%', '%level%', '%body%'],
            [date(self::$date_format), strtoupper(substr($level, 0, 4)), $message],
            self::$format
        );
        $output = $this->interpolate($output, $context);
        echo $this->colorize($output, $level) . "\n";
    }

    private function stringify($item)
    {
        if (is_object($item) && method_exists($item, '__toString')) {
            return $item;
        }
        if (is_string($item) || is_numeric($item)) {
            return $item;
        }
        if (is_callable($item)) {
            return '{Closure}';
        }
        if (is_bool($item)) {
            return $item ? '*True*' : '*False*';
        }
        if (is_array($item)) {
            return json_encode(
                $item,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_LINE_TERMINATORS
            );
        }
        if (is_resource($item)) {
            return '{Resource}';
        }
        if (is_null($item)) {
            return 'NULL';
        }
        return '{Not Stringable Object:' . get_class($item) . '}';
    }

    private function interpolate($message, array $context = [])
    {
        if (is_array($message)) {
            return $message;
        }

        $replace = [];
        foreach ($context as $key => $value) {
            $replace['{' . $key . '}'] = $this->stringify($value);
        }

        return strtr($message, $replace);
    }
}
