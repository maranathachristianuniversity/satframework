<?php
/**
 * satframework.
 * MVC PHP Framework for quick and fast PHP Application Development.
 * Copyright (c) 2020, IT Maranatha
 *
 * @author Didit Velliz
 * @link https://github.com/maranathachristianuniversity/sat-framework
 * @since Version 0.9.3
 */

namespace satframework\pdc;

use Exception;
use Memcached;
use pte\PteCache;
use satframework\config\Config;
use satframework\Response;

/**
 * Class Template
 * @package satframework\pdc
 */
class Template implements Pdc, PteCache
{

    var $key;
    var $value;
    var $switch;

    /**
     * @var Memcached
     */
    var $cache;

    /**
     * @param $clause
     * @param $command
     * @param $value
     */
    public function SetCommand($clause, $command, $value = null)
    {
        $this->key = $clause;
        $this->value = $command;
        $this->switch = $value;
    }

    /**
     * @param Response &$response
     * @return mixed
     * @throws Exception
     */
    public function SetStrategy(Response &$response)
    {
        switch ($this->value) {
            case 'master':
                if (strcasecmp(str_replace(' ', '', $this->switch), 'false') === 0) {
                    $response->useMasterLayout = false;
                }
                break;
            case 'html':
                if (strcasecmp(str_replace(' ', '', $this->switch), 'false') === 0) {
                    $response->useHtmlLayout = false;
                }
                break;
            case 'cache':
                if (strcasecmp(str_replace(' ', '', $this->switch), 'true') === 0) {
                    $cacheConfig = Config::Data('app')['cache'];
                    $this->cache = new Memcached();
                    $this->cache->addServer($cacheConfig['host'], $cacheConfig['port']);
                    $response->cacheDriver = $this;
                }
                break;
        }

        return true;
    }

    /**
     * @param $templateKeys
     * @return array|false
     */
    public function GetTemplate($templateKeys)
    {
        $templateKeys = hash('ripemd160', $templateKeys);
        return $this->cache->get($templateKeys);
    }

    /**
     * @param $templateKeys
     * @param $templateData
     * @return array
     */
    public function SetTemplate($templateKeys, $templateData)
    {
        $templateKeys = hash('ripemd160', $templateKeys);
        $this->cache->set($templateKeys, $templateData, 120);
        return $this->cache->get($templateKeys);
    }
}