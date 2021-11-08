<?php

namespace OneBot\V12\Driver\Config;

use OneBot\V12\Exception\OneBotException;
use OneBot\V12\Utils;

class WorkermanConfig implements Config
{
    /** @var array */
    private $object;

    /**
     * SwooleDriverConfig constructor.
     * @param $file
     * @throws OneBotException
     */
    public function __construct($file) {
        if (!file_exists($file)) throw new OneBotException("配置文件不存在！");
        $json = json_decode(file_get_contents($file), true);
        if ($json === null) throw new OneBotException("配置文件格式错误，必须为json！");
        $this->validate();
        $this->object = $json;
    }

    public function getEnabledCommunications() {
        $enabled = [];
        foreach ($this->object as $k => $v) {
            if ($k === "http" && ($v["enable"] ?? false) === true) {
                $enabled[] = [
                    "type" => $k,
                    "host" => $v["host"] ?? "127.0.0.1",
                    "port" => $v["port"] ?? 9600,
                    "access_token" => $v["access_token"] ?? null
                ];
            }
            if ($k === "http_webhook" && Utils::isAssocArray($v) && ($v["enable"] ?? false) === true) {
                $enabled[] = [
                    "type" => $k,
                    "url" => $v["url"],
                    "access_token" => $v["access_token"] ?? null
                ];
            }
            if ($k === "http_webhook" && !Utils::isAssocArray($v)) {
                foreach ($v as $ks => $vs) {
                    if (($vs["enable"] ?? false) === true) {
                        $enabled[] = [
                            "type" => $k,
                            "url" => $v["url"],
                            "access_token" => $v["access_token"] ?? null
                        ];
                    }
                }
            }
            if ($k === "ws_reverse" && Utils::isAssocArray($v) && ($v["enable"] ?? false) === true) {
                $enabled[] = [
                    "type" => $k,
                    "url" => $v["url"],
                    "access_token" => $v["access_token"] ?? null
                ];
            }
            if ($k === "ws_reverse" && !Utils::isAssocArray($v)) {
                foreach ($v as $ks => $vs) {
                    if (($vs["enable"] ?? false) === true) {
                        $enabled[] = [
                            "type" => $k,
                            "url" => $v["url"],
                            "access_token" => $v["access_token"] ?? null
                        ];
                    }
                }
            }
            if ($k === "ws" && ($v["enable"] ?? false) === true) {
                $enabled[] = [
                    "type" => $k,
                    "host" => $v["host"] ?? "127.0.0.1",
                    "port" => $v["port"] ?? 9600,
                    "access_token" => $v["access_token"] ?? null
                ];
            }
        }
        return $enabled;
    }

    /**
     * 校验配置文件是否符合onebot标准
     * TODO: 完成检验配置文件（workerman）
     */
    private function validate() {

    }
}