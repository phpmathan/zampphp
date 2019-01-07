<?php

namespace Zamp\Cache;

/**
 *  Always namespace your $key with module prefix
 *  
 *  Example: Core/Database/Connection/Info => here `Core` is the module name
 *  Example: Payments/payload => here `Payments` is the module name
 */
abstract class AbstractClass {
    protected $ttl = 3600;
    protected $config = [];
    
    public function __construct($config, $ttl) {
        $this->ttl = (int) $ttl;
        $this->config = $config;
    }
    
    abstract public function driverName();
    
    abstract public function get($key);
    
    abstract public function set($key, $value, $ttl=null);
    
    abstract public function delete($key);
    
    abstract public function clear();
    
    protected function getCache($key, $value) {
        array_shift($key);
        
        if(!$key)
            return $value ?? null;
        
        foreach($key as $k) {
            if(isset($value[$k]))
                $value = $value[$k];
            else
                return null;
        }
        
        return $value;
    }
}
/* END OF FILE */