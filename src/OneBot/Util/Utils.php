<?php

declare(strict_types=1);

namespace OneBot\Util;

use PASVL\Validation\ValidatorBuilder;

class Utils
{
    public static $cache = [];

    public static function isAssocArray(array $arr): bool
    {
        return array_values($arr) !== $arr;
    }

    public static function separatorToCamel($name, $separator = '_'): string
    {
        $name = $separator . str_replace($separator, ' ', strtolower($name));
        return ltrim(str_replace(' ', '', ucwords($name)), $separator);
    }

    public static function camelToSeparator($str, $separator = '_'): string
    {
        return strtolower(preg_replace('/([a-z])([A-Z])/', '$1' . $separator . '$2', $str));
    }

    public static function msgToString($message): string
    {
        $result = '';
        if (is_array($message)) {
            foreach ($message as $v) {
                if ($v['type'] === 'text') {
                    $result .= $v['data']['text'];
                }
            }
        } else {
            $result = $message;
        }
        return $result;
    }

    /**
     * 验证 $input 是否符合指定 $pattern.
     *
     * @see https://github.com/lezhnev74/pasvl
     */
    public static function validateArray(array $pattern, array $input)
    {
        $builder = ValidatorBuilder::forArray($pattern);
        $builder->build()->validate($input);
    }
}
