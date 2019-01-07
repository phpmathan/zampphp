<?php

namespace Zamp\Cache;

use \Zamp\General;

class Redis extends AbstractClass {
    protected $config = [
        'host' => '127.0.0.1',
        'port' => 6379,
        'timeout' => 0,
        'password' => null,
        'database' => null,
    ];
    
    public $redisServer;
    
    public function __construct($config, $ttl) {
        if(!extension_loaded('Redis'))
            throw new \Exception('Could not Initialize Redis Cache');
        
        $config = array_merge($this->config, $config);
        
        $this->redisServer = new \Redis();
        
        if(!$this->redisServer->connect($config['host'], $config['port'], $config['timeout']))
            throw new \Exception('Redis connection failed.');
        
        if(isset($config['password']) && !$this->redisServer->auth($config['password']))
            throw new \Exception('Redis authentication failed.');
        
        if(isset($config['database']))
            $this->redisServer->select((int) $config['database']);
        
        parent::__construct($config, $ttl);
    }
    
    public function driverName() {
        return 'Redis';
    }
    
    public function get($key) {
        $key = explode('/', $key);
        
        $value = $this->redisServer->get($key[0]);
        
        if(($value = @unserialize($value)) === false)
            return null;
        
        return $this->getCache($key, $value);
    }
    
    public function set($key, $value, $ttl=null) {
        $ttl = $ttl ?? $this->ttl;
        $ttl = (int) $ttl;
        
        $key = explode('/', $key);
        $module = array_shift($key);
        
        if($key)
            $value = General::setMultiArrayValue($key, $value);
        
        $previousData = $this->redisServer->get($module);
        if(($previousData = @unserialize($previousData)) === false)
            $previousData = [];
        
        if(isset($value) && (array) $value === $value && (array) $previousData === $previousData)
            $value = General::arrayMergeRecursiveDistinct($previousData, $value);
        
        return $this->redisServer->setEx($module, $ttl, serialize($value));
    }
    
    public function delete($key) {
        $key = explode('/', $key);
        $module = array_shift($key);
        
        if(!$key)
            return $this->redisServer->del($module);
        
        $value = $this->redisServer->get($module);
        if(($value = @unserialize($value)) === false)
            $value = [];
        
        if((array) $value === $value) {
            General::unsetMultiArrayValue($key, $value);
            
            if(!$value)
                return $this->redisServer->del($module);
            
            return $this->redisServer->setEx($module, $this->ttl, serialize($value));
        }
        
        return $this->redisServer->del($module);
    }
    
    public function clear() {
        return $this->redisServer->flushDB();
    }
}
/* END OF FILE */