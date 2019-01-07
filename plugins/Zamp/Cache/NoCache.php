<?php

namespace Zamp\Cache;

class NoCache extends AbstractClass {
    protected $config = [];
    
    public function driverName() {
        return 'NoCache';
    }
    
    public function get($key) {
        return null;
    }
    
    public function set($key, $value, $ttl=null) {
        return true;
    }
    
    public function delete($key) {
        return true;
    }
    
    public function clear() {
        return true;
    }
}
/* END OF FILE */