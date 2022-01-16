<?php

declare(strict_types=1);

namespace OneBot\Logger\Console;

use OneBot\Util\MPUtils;
use Psr\Log\AbstractLogger;
use Psr\Log\InvalidArgumentException;
use Psr\Log\LogLevel;

class ConsoleLogger extends AbstractLogger
{
    public static $format = '[%date%] [%level%] %process%%body%';

    public static $date_format = 'Y-m-d H:i:s';

    /**
     * @var string[] 颜色表
     */
    protected static $styles = [
        LogLevel::EMERGENCY => ['blink', 'white', 'bg_bright_red'],
        LogLevel::ALERT => ['white', 'bg_bright_red'],
        LogLevel::CRITICAL => ['underline', 'red'],
        LogLevel::ERROR => ['red'],
        LogLevel::WARNING => ['bright_yellow'],
        LogLevel::NOTICE => ['cyan'],
        LogLevel::INFO => ['green'],
        LogLevel::DEBUG => ['gray'],
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

    public function __construct($logLevel = LogLevel::INFO)
    {
        self::$logLevel = array_flip(self::$levels)[$logLevel];
    }

    public function colorize($string, $level): string
    {
        $string = $this->stringify($string);
        $styles = self::$styles[$level] ?? [];
        return ConsoleColor::apply($styles, $string)->__toString();
    }

    public function trace(): void
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

    public function log($level, $message, array $context = []): void
    {
        if (!in_array($level, self::$levels, true)) {
            throw new InvalidArgumentException();
        }

        if (array_flip(self::$levels)[$level] > self::$logLevel) {
            return;
        }

        $output = str_replace(
            ['%date%', '%level%', '%body%', '%process%'],
            [date(self::$date_format), strtoupper(substr($level, 0, 4)), $message, MPUtils::getProcessLogName()],
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
