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
        'bold' => '1', // ??????
        'dark' => '2', // ??????
        'italic' => '3', // ??????
        'underline' => '4', // ?????????
        'blink' => '5', // ??????
        'rapid_blink' => '6', // ??????????????????????????????
        'reverse' => '7', // ??????
        'concealed' => '8', // ????????????????????????
        'strike' => '9', // ?????????

        'default' => '39', // ????????????
        'black' => '30', // ??????
        'red' => '31', // ??????
        'green' => '32', // ??????
        'yellow' => '33', // ????????????????????????????????????????????? bright_yellow ??????
        'blue' => '34', // ??????
        'magenta' => '35', // ??????
        'cyan' => '36', // ??????
        //        'white' => '37', // ??????????????????????????????
        'gray' => '37',
        'white' => '97', // ??????????????????

        'bright_black' => '90', // ????????????????????????
        'bright_red' => '91', // ?????????
        'bright_green' => '92', // ?????????
        'bright_yellow' => '93', // ?????????
        'bright_blue' => '94', // ?????????
        'bright_magenta' => '95', // ?????????
        'bright_cyan' => '96', // ?????????
        'bright_white' => '97', // ?????????

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
