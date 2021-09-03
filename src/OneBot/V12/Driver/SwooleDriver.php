<?php


namespace OneBot\V12\Driver;


use OneBot\V12\ActionResponse;
use OneBot\V12\Console;
use OneBot\V12\CoreActionInterface;
use OneBot\V12\Driver\Config\Config;
use OneBot\V12\Object\ActionObject;
use OneBot\V12\Object\EventObject;
use OneBot\V12\OneBot;
use OneBot\V12\OneBotException;
use OneBot\V12\Utils;
use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\Http\Server as SwooleHttpServer;
use Swoole\Server;
use Swoole\WebSocket\Frame;
use Swoole\WebSocket\Server as SwooleWebSocketServer;

class SwooleDriver implements Driver
{
    /** @var Config|null */
    private $config = null;
    /**
     * @var SwooleWebSocketServer|SwooleHttpServer
     */
    private $server;

    public function __construct() {

    }

    public function setConfig(Config $config) {
        $this->config = $config;
    }

    public function getName() {
        return "swoole";
    }

    public function emitOBEvent(EventObject $event) {

    }

    public function run() {
        $enabled_com = $this->config->getEnabledCommunications();
        $has_ws = false;
        $has_http = false;
        foreach ($enabled_com as $k => $v) {
            if ($v["type"] == "ws") $has_ws = $k;
            if ($v["type"] == "http") $has_http = $k;
        }
        if ($has_ws !== false) {
            $this->server = new SwooleWebSocketServer($enabled_com[$has_ws]["host"], $enabled_com[$has_ws]["port"]);
            $this->initServer();
            if ($has_http !== false) {
                Console::warning("检测到同时开启了http和正向ws，http的配置项将被忽略。");
                $this->initHttpServer();
            }
            $this->initWebSocketServer();
        } elseif ($has_http !== false) {
            //echo "新建http服务器.\n";
            $this->server = new SwooleHttpServer($enabled_com[$has_http]["host"], $enabled_com[$has_http]["port"]);
            $this->initHttpServer();
        } else {
            go(function () {
                //TODO: 在协程状态下启动纯客户端模式
            });
        }
        if ($this->server !== null) $this->server->start();
    }

    /**
     * 初始化使用ws通信方式的注册事件
     */
    private function initWebSocketServer() {
        $this->server->on("open", [$this, "onOpenEvent"]);
        $this->server->on("message", [$this, "onMessageEvent"]);
        $this->server->on("close", [$this, "onCloseEvent"]);
    }

    private function initServer() {
        $this->server->set([
            'max_coroutine' => 300000,
            'max_wait_time' => 5
        ]);
        $this->server->on("workerstart", function(Server $server) {
            echo("已启动服务器 at " . $server->host . ":" . $server->port);
        });
    }

    /**
     * 初始化使用http通信方式的注册事件
     */
    private function initHttpServer() {
        $this->server->on("request", [$this, "onRequestEvent"]);
    }

    private function onOpenEvent(?SwooleWebSocketServer $server, Request $request) {
        //TODO: 编写swoole收到正向ws连接请求的流程
    }

    private function onMessageEvent(?SwooleWebSocketServer $server, Frame $frame) {
        //TODO: 编写swoole收到websocket包的流程
    }

    public function onRequestEvent(Request $request, Response $response) {
        if (($request->header["content-type"] ?? null) === "application/json") {
            $raw_type = ONEBOT_JSON;
            $obj = $request->rawContent();
            $json = json_decode($obj, true);
            if (!isset($json["action"])) {
                //TODO: 错误处理
            }
            $action_obj = new ActionObject($json["action"], $json["params"] ?? [], $json["echo"] ?? null);
        } elseif (($request->header["content-type"] ?? null) === "application/msgpack") {
            $raw_type = ONEBOT_MSGPACK;
            $action_obj = new ActionObject("T");
            //TODO: 完成对msgpack格式的处理
        } else {
            throw new OneBotException();
            //TODO: 处理非法的Content类型
        }
        switch (Utils::getActionType($action_obj)) {
            case ONEBOT_CORE_ACTION:
                $handler = OneBot::getInstance()->getCoreActionHandler();
                $emit = Utils::getCoreActionMethods()[$action_obj->action];
                $handler->echo = $action_obj->echo;
                $response_obj = $handler->$emit($action_obj->params, $action_obj->echo);
                break;
            case ONEBOT_EXTENDED_ACTION:
                $handler = OneBot::getInstance()->getExtendedActions()[$action_obj->action];
                $response_obj = call_user_func($handler, $action_obj->params, $action_obj->echo);
                break;
            default:
                $response_obj = new ActionResponse();
                $response_obj->retcode = -1;
                break;
        }
        $this->emitHttpResponse($response, $response_obj, $raw_type);
    }

    private function onCloseEvent(?Server $server, $fd) {
        //TODO: 编写swoole断开ws连接请求的流程
    }

    private function emitHttpResponse(Response $response, ActionResponse $response_obj, $type = ONEBOT_JSON) {
        switch ($type) {
            case ONEBOT_JSON:
                $data = json_encode($response_obj, JSON_UNESCAPED_UNICODE);
                $response->header("Content-Type", "application/json");
                $response->end($data);
                break;
        }
    }
}