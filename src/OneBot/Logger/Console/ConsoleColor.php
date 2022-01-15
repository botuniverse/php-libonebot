<?php

declare(strict_types=1);

namespace OneBot\Logger\Console;

class ConsoleColor
{
    public const RESET = 0;

    public const STYLES = [
        'none' => null,
        'bold' => '1', // 加粗
        'dark' => '2', // 昏暗
        'italic' => '3', // 倾斜
        'underline' => '4', // 下划线
        'blink' => '5', // 闪烁
        'rapid_blink' => '6', // 快速闪烁，兼容性不佳
        'reverse' => '7', // 反转
        'concealed' => '8', // 遮盖，兼容性不佳
        'strike' => '9', // 删除线

        'default' => '39', // 默认颜色
        'black' => '30', // 黑色
        'red' => '31', // 红色
        'green' => '32', // 绿色
        'yellow' => '33', // 黄色，各终端表现不一，建议使用 bright_yellow 替代
        'blue' => '34', // 蓝色
        'magenta' => '35', // 紫色
        'cyan' => '36', // 青色
        //        'white' => '37', // 白色，实际表现为灰色
        'gray' => '37',
        'white' => '97', // 此处为亮白色

        'bright_black' => '90', // 亮黑色（暗灰色）
        'bright_red' => '91', // 亮红色
        'bright_green' => '92', // 亮绿色
        'bright_yellow' => '93', // 亮黄色
        'bright_blue' => '94', // 亮蓝色
        'bright_magenta' => '95', // 亮紫色
        'bright_cyan' => '96', // 亮青色
        'bright_white' => '97', // 亮白色

        'bg_default' => '49',
        'bg_black' => '40',
        'bg_red' => '41',
        'bg_green' => '42',
        'bg_yellow' => '43',
        'bg_blue' => '44',
        'bg_magenta' => '45',
        'bg_cyan' => '46',
        'bg_gray' => '47',
        'bg_white' => '107',

        'bg_bright_black' => '100',
        'bg_bright_red' => '101',
        'bg_bright_green' => '102',
        'bg_bright_yellow' => '103',
        'bg_bright_blue' => '104',
        'bg_bright_magenta' => '105',
        'bg_bright_cyan' => '106',
        'bg_bright_white' => '107',
    ];

    protected $styles = [];

    protected $text = '';

    public function __call($name, $arguments)
    {
        $this->addStyle($name);
        if (isset($arguments[0])) {
            $this->setText($arguments[0]);
        }
        return $this;
    }

    public static function __callStatic($name, $arguments)
    {
        $instance = new self();
        $instance->addStyle($name);
        if (isset($arguments[0])) {
            $instance->setText($arguments[0]);
        }
        return $instance;
    }

    public function __toString()
    {
        $style_code = $this->getStylesCode();

        return sprintf("\033[%sm%s\033[%dm", $style_code, $this->text, self::RESET);
    }

    public static function apply(array $styles, string $text): ConsoleColor
    {
        $instance = new self();
        $instance->setText($text);
        $instance->applyStyles($styles);
        return $instance;
    }

    public function applyStyles(array $styles): ConsoleColor
    {
        $this->styles = array_merge($this->styles, $styles);
        return $this;
    }

    public function setText(string $text): ConsoleColor
    {
        $this->text = $text;
        return $this;
    }

    protected function addStyle(string $style): ConsoleColor
    {
        $style = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $style));
        if (array_key_exists($style, self::STYLES)) {
            $this->styles[] = $style;
        }
        return $this;
    }

    protected function getStylesCode(): string
    {
        array_walk($this->styles, static function (&$style) {
            // 4bit (classic)
            if (array_key_exists($style, self::STYLES)) {
                $style = self::STYLES[$style];
                return;
            }
            // 8bit (256color)
            if (str_contains($style, 'color')) {
                preg_match('~^(bg_)?color_(\d{1,3})$~', $style, $matches);
                $type = $matches[1] === 'bg_' ? '48' : '38';
                $value = $matches[2];
                $style = "{$type};5;{$value}";
                return;
            }
            // 24bit (rgb)
            if (str_contains($style, 'rgb')) {
                preg_match('~^(bg_)?rgb_(\d{1,3})_(\d{1,3})_(\d{1,3})$~', $style, $matches);
                $type = $matches[1] === 'bg_' ? '48' : '38';
                [, , $r, $g, $b] = $matches;
                $style = "{$type};2;{$r};{$g};{$b}";
            }
        });

        return implode(';', $this->styles);
    }
}
