<?php

namespace Zamp;

class Application extends Base {
    /*
    // To set custom configuration path and its file name
    protected static $_moduleConfig = [
        'namespace' => 'modules/Core/mongoDb',
        'fileName' => 'Mongo',
    ];
    */
    
    final public static function moduleName() {
        static $moduleName = [];
        
        $className = static::class;
        
        if(!isset($moduleName[$className])) {
            $module = preg_replace('~^'.preg_quote(Core::system()->config['bootstrap']['applicationNameSpace']).'\\\~', '', $className, 1, $isFound);
            $moduleName[$className] = $isFound ?explode('\\', $module, 2)[0] :$className;
        }
        
        return $moduleName[$className];
    }
    
    final public static function modulePath() {
        static $modulePath = [];
        
        $className = static::class;
        
        if(!isset($modulePath[$className])) {
            $module = (new \ReflectionClass($className))->getFileName();
            $modulePath[$className] = preg_replace('~/(Controller|Model)/[a-zA-Z0-9\_]+\.php$~', '', $module, 1, $isFound);
            
            if(!$isFound)
                $modulePath[$className] = substr($module, 0, strrpos($module, '/'));
        }
        
        return $modulePath[$className];
    }
    
    /**
     *  To get module's configuration
     *  Core::moduleConfig();
     *  Core::moduleConfig('configKey');
     *  
     *  To set module's configuration
     *  Core::moduleConfig('configKey', 'appendValue');
     *  Core::moduleConfig('configKey', 'replaceValue', false);
     */
    final public static function moduleConfig($key=null, $value=null, $merge=true) {
        $moduleName = $confFileName = self::moduleName();
        $namespace = 'modules/'.$moduleName;
        
        if(!empty(static::$_moduleConfig)) {
            $confFileName = static::$_moduleConfig['fileName'];
            $namespace = static::$_moduleConfig['namespace'];
        }
        
        Core::system()->checkAndLoadConfiguration($namespace, $confFileName, $moduleName);
        
        if($key)
            $namespace .= '/'.$key;
        
        if($value === null)
            return getConf($namespace);
        
        setConf($namespace, $value, $merge);
        
        return $value;
    }
    
    /**
     *  To get module's runtime cache
     *  Core::moduleRunTime();
     *  Core::moduleRunTime('cacheKey');
     *  
     *  To set module's runtime cache
     *  Core::moduleRunTime('cacheKey', 'appendValue');
     *  Core::moduleRunTime('cacheKey', 'replaceValue', false);
     */
    final public static function moduleRunTime($key=null, $value=null, $merge=true) {
        static $cache = [];
        
        $namespace = 'modules'.self::moduleName();
        
        if($key)
            $namespace .= '/'.$key;
        
        if($value === null)
            return getConf($namespace, $cache);
        
        $cache = setConf($namespace, $value, $merge, $cache);
        
        return $value;
    }
    
    /**
     *  To get module's cache
     *  Core::moduleCache();
     *  Core::moduleCache('cacheKey');
     *  
     *  To set module's cache
     *  Core::moduleCache('cacheKey', 'appendValue');
     *  
     *  To delete module's cache
     *  Core::moduleCache(null, '--');
     *  Core::moduleCache('cacheKey', '--');
     */
    final public static function moduleCache($key=null, $value=null, $ttl=null) {
        $namespace = 'modules'.self::moduleName();
        
        if($key)
            $namespace .= '/'.$key;
        
        if($value === '--')
            return Core::system()->cache->delete($namespace);
        
        if($value === null)
            return Core::system()->cache->get($namespace);
        
        Core::system()->cache->set($namespace, $value, $ttl);
        
        return $value;
    }
    
    /**
     *  To get module's session
     *  Core::moduleSession();
     *  Core::moduleSession('cacheKey');
     *  
     *  To set module's session
     *  Core::moduleSession('cacheKey', 'appendValue');
     *  
     *  To delete module's session
     *  Core::moduleSession(null, '--');
     *  Core::moduleSession('cacheKey', '--');
     */
    final public static function moduleSession($key=null, $value=null, $unsetBeforeSet=false) {
        $namespace = 'modules/'.self::moduleName();
        
        if($key)
            $namespace .= '/'.$key;
        
        if($value === '--')
            return deleteSession($namespace);
        
        if($value === null)
            return getSession($namespace);
        
        setSession($namespace, $value, $unsetBeforeSet);
        
        return $value;
    }
    
    public function __call($fnName, $args) {
        return $args ?call_user_func_array([Core::system(), $fnName], $args) :Core::system()->$fnName();
    }
    
    public function __get($key) {
        $system = Core::system();
        
        if(isset($system->_runTimeProperties[$key])) {
            if($system->_runTimeProperties[$key]['type'] == 'Object')
                $this->$key =& $system->_runTimeProperties[$key]['data'];
            
            return $system->_runTimeProperties[$key]['data'];
        }
        
        if(isset($system->_restrictedProperties[$key])) {
            if($system->_restrictedProperties[$key] == 'Object')
                $this->$key =& $system->$key;
            
            return $system->$key;
        }
        
        $className = static::class;
        $className = substr($className, 0, strrpos($className, '\\')).'\\'.$key;
        
        $obj = Core::getInstance($className);
        $this->$key =& $obj;
        
        return $this->$key;
    }
    
    public function __set($key, $value) {
        if(isset(Core::system()->_restrictedProperties[$key]))
            throw new Exceptions\ReservedProperty("Property `{$key}` is reserved for Zamp PHP!");
        
        $this->$key = $value;
    }
}
/* END OF FILE */