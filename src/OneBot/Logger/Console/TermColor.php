<?php

declare(strict_types=1);

namespace OneBot\Logger\Console;

class TermColor
{
    public const RESET = "\e[0m";      // 重设样式

    public const BOLD = "\e[1m";       // 加粗

    public const ITALIC = "\e[3m";     // 斜体

    public const UNDERSCORE = "\e[4m"; // 下划线

    public const BLINK = "\e[5m";      // 闪烁

    public const HIDE = "\e[8m";       // 隐藏

    /**
     * 输出 8 位的颜色 (包括前景色和背景色).
     *
     * @param mixed $code
     */
    public static function color8($code): string
    {
        return "\e[{$code}m";
    }

    /**
     * 输出 256 位的前景文字颜色 (通过8位256色颜色码).
     *
     * @param mixed $code
     */
    public static function frontColor256($code): string
    {
        return "\e[38;5;{$code}m";
    }

    /**
     * 输出 256 位的前景文字颜色 (通过rgb).
     *
     * @param mixed $r
     * @param mixed $g
     * @param mixed $b
     */
    public static function frontColor256rgb($r, $g, $b): string
    {
        return "\e[38;2;{$r};{$g};{$b}m";
    }

    /**
     * 输出 256 位的背景文字颜色 (通过8位256色颜色码).
     *
     * @param mixed $code
     */
    public static function bgColor256($code): string
    {
        return "\e[48;5;{$code}m";
    }

    /**
     * 输出 256 位的前景文字颜色 (通过rgb).
     *
     * @param mixed $r
     * @param mixed $g
     * @param mixed $b
     */
    public static function bgColor256rgb($r, $g, $b): string
    {
        return "\e[48;2;{$r};{$g};{$b}m";
    }
}
