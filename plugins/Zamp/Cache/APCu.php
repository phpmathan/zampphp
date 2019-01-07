<?php

namespace Zamp\Cache;

use \Zamp\General;

class APCu extends AbstractClass {
    protected $config = [];
    
    public function __construct($config, $ttl) {
        if(!function_exists('\apcu_cache_info'))
            throw new \Zamp\Exceptions\CacheInitFailed('Could not Initialize APCu Cache');
        
        parent::__construct($config, $ttl);
    }
    
    public function driverName() {
        return 'APCu';
    }
    
    public function get($key) {
        $key = explode('/', $key);
        return $this->getCache($key, apcu_fetch($key[0]));
    }
    
    public function set($key, $value, $ttl=null) {
        $ttl = $ttl ?? $this->ttl;
        $ttl = (int) $ttl;
        
        $key = explode('/', $key);
        $module = array_shift($key);
        
        if($key)
            $value = General::setMultiArrayValue($key, $value);
        
        $previousData = apcu_fetch($module);
        
        if(isset($value) && (array) $value === $value && (array) $previousData === $previousData)
            $value = General::arrayMergeRecursiveDistinct($previousData, $value);
        
        return apcu_store($module, $value, $ttl);
    }
    
    public function delete($key) {
        $key = explode('/', $key);
        $module = array_shift($key);
        
        if(!$key)
            return apcu_delete($module);
        
        $value = apcu_fetch($module);
        
        if((array) $value === $value) {
            General::unsetMultiArrayValue($key, $value);
            
            if(!$value)
                return apcu_delete($module);
            
            return apcu_store($module, $value, $this->ttl);
        }
        
        return apcu_delete($module);
    }
    
    public function clear() {
        return apcu_clear_cache();
    }
}
/* END OF FILE */
