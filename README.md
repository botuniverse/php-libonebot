<!--suppress HtmlDeprecatedAttribute -->
<p align="center">
  <a href="https://github.com/botuniverse/php-libonebot/releases">
    <img alt="Version" src="https://img.shields.io/github/v/release/botuniverse/php-libonebot?include_prereleases&logo=github&style=flat-square" />
  </a>
  <img alt="GitHub Workflow Status" src="https://img.shields.io/github/workflow/status/botuniverse/php-libonebot/Test?logo=github&style=flat-square" />
  <img alt="License" src="https://img.shields.io/github/license/botuniverse/php-libonebot?style=flat-square&logo=open%20source%20initiative&logoColor=white" />
  <img alt="Packagist PHP Version Support" src="https://img.shields.io/packagist/php-v/onebot/libonebot?color=777bb3&logo=php&logoColor=white&style=flat-square" />
</p>

# php-libonebot

PHP 的 LibOneBot 库。LibOneBot 可以帮助 OneBot 实现者快速在新的聊天机器人平台实现 OneBot v12 接口标准。

基于 LibOneBot 实现 OneBot 时，OneBot 实现者只需专注于编写与聊天机器人平台对接的逻辑，包括通过长轮询或 webhook 方式从机器人平台获得事件，并将其转换为 OneBot 事件，以及处理 OneBot
动作请求，并将其转换为对机器人平台 API 的调用。

**当前版本还在开发中，在发布正式版之前此库内的接口可能会发生较大变动。**

开发进度见 [更新日志](/docs/update.md)。

## 使用

```shell
composer require onebot/libonebot
```

## 尝试 Demo

在 require 下载 libob 库后，新建文件 `demo.php` 和 `demo.json`，并在 `demo.php` 中写如下代码：

```php
<?php

declare(strict_types=1);

require_once 'vendor/autoload.php';

$ob = new \OneBot\V12\OneBot('repl', 'qq', 'REPL-1');
$ob->setLogger(new \OneBot\Logger\Console\ConsoleLogger());
$ob->setDriver(
    // 此处也可以在 Linux 系统下安装 swoole 扩展后使用 SwooleDriver() 拥有协程能力
    new \OneBot\Driver\WorkermanDriver(), 
    new \OneBot\V12\Config\Config('demo.json')
);
$ob->setActionHandlerClass(\OneBot\V12\Action\ReplAction::class);
$ob->run();
```

在 `demo.json` 中写如下代码：

```json
{
    "lib": {
        "db": false
    },
    "communications": {
        "http": {
            "enable": true,
            "host": "0.0.0.0",
            "port": 9600,
            "event_enabled": true,
            "event_buffer_size": 0
        }
    }
}
```

此 Demo 以一个命令行交互的方式使用 LibOneBot 快速完成了一个 OneBot 实现，命令行中输入内容即可发送到 OneBot，使用 HTTP 或 WebSocket 发送给 LibOneBot 后可以将信息显示在终端内。

```bash
# 运行 OneBot 实现
php demo.php
```

启动后可以利用 Postman 或 Curl 等工具发起请求，以 OneVot V12 协议的[发送消息动作](https://12.onebot.dev/interface/action/message/)为例：

```shell
curl --location --request POST 'http://localhost:9600/' \
--header 'Content-Type: application/json' \
--data-raw '{
    "action": "send_message",
    "params": {
        "detail_type": "group",
        "group_id": "12467",
        "message": [
            {
                "type": "text",
                "data": {
                    "text": "我是文字巴拉巴拉巴拉"
                }
            }
        ]
    }
}'
```

你应该可以看到 OneBot 命令行中出现以下消息：

```shell
[2021-11-18 18:44:39] [INFO] 我是文字巴拉巴拉巴拉
```

并收到以下响应：

```text
{"status":"ok","retcode":0,"data":{"message_id":5007842},"message":""}%
```
