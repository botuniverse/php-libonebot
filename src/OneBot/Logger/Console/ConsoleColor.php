<?php

declare(strict_types=1);

namespace OneBot\Logger\Console;

use OneBot\Util\Utils;

/**
 * @method static none($text = null)
 * @method static bold($text = null)
 * @method static dark($text = null)
 * @method static italic($text = null)
 * @method static underline($text = null)
 * @method static blink($text = null)
 * @method static rapid_blink($text = null)
 * @method static reverse($text = null)
 * @method static concealed($text = null)
 * @method static strike($text = null)
 * @method static default($text = null)
 * @method static black($text = null)
 * @method static red($text = null)
 * @method static green($text = null)
 * @method static yellow($text = null)
 * @method static blue($text = null)
 * @method static magenta($text = null)
 * @method static cyan($text = null)
 * @method static gray($text = null)
 * @method static white($text = null)
 * @method static bright_black($text = null)
 * @method static bright_red($text = null)
 * @method static bright_green($text = null)
 * @method static bright_yellow($text = null)
 * @method static bright_blue($text = null)
 * @method static bright_magenta($text = null)
 * @method static bright_cyan($text = null)
 * @method static bright_white($text = null)
 * @method static bg_default($text = null)
 * @method static bg_black($text = null)
 * @method static bg_red($text = null)
 * @method static bg_green($text = null)
 * @method static bg_yellow($text = null)
 * @method static bg_blue($text = null)
 * @method static bg_magenta($text = null)
 * @method static bg_cyan($text = null)
 * @method static bg_gray($text = null)
 * @method static bg_white($text = null)
 * @method static bg_bright_black($text = null)
 * @method static bg_bright_red($text = null)
 * @method static bg_bright_green($text = null)
 * @method static bg_bright_yellow($text = null)
 * @method static bg_bright_blue($text = null)
 * @method static bg_bright_magenta($text = null)
 * @method static bg_bright_cyan($text = null)
 * @method static bg_bright_white($text = null)
 */
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
        $style = Utils::separatorToCamel($style);
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
