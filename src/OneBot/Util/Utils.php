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

    public static function separatorToCamel(string $string, string $separator = '_'): string
    {
        $string = $separator . str_replace($separator, ' ', strtolower($string));
        return ltrim(str_replace(' ', '', ucwords($string)), $separator);
    }

    public static function camelToSeparator(string $string, string $separator = '_'): string
    {
        return strtolower(ltrim(preg_replace('/[A-Z]([A-Z](?![a-z]))*/', $separator . '$0', $string), '_'));
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
