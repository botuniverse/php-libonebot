<?php /** @noinspection PhpDefineCanBeReplacedWithConstInspection */

define("ONEBOT_VERSION", "12");
define("ONEBOT_LIBOB_VERSION", "0.1.0");

define("ONEBOT_JSON", 1);
define("ONEBOT_MSGPACK", 2);

define("ONEBOT_CORE_ACTION", 1);
define("ONEBOT_EXTENDED_ACTION", 2);
define("ONEBOT_UNKNOWN_ACTION", 0);

function ob_dump($var, ...$moreVars) {
    \Symfony\Component\VarDumper\VarDumper::dump($var);
    foreach ($moreVars as $v) {
        \Symfony\Component\VarDumper\VarDumper::dump($v);
    }
    if (1 < func_num_args()) {
        return func_get_args();
    }
    return $var;
}