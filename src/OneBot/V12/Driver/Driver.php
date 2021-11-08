<?php


namespace OneBot\V12\Driver;


use OneBot\V12\Driver\Config\Config;
use OneBot\V12\Object\EventObject;

interface Driver
{
    /**
     * @return string
     */
    public function getName();

    public function setConfig(Config $config);

    public function emitOBEvent(EventObject $event);

    public function initComm();

    public function getConfig(): Config;

    public function run();
}