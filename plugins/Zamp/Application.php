<?php

namespace Zamp;

class Application extends Base {
    private $_appName;
    protected $_runTimeCache = [];
    /*
    protected $onInstanceCreate = [
        'call' => [$this, 'methodName'],
        'call' => [self::class, 'methodName'],
        'call' function(...$params) {
            
        },
        'call' => ModelName::class.'/method',
        'args' => ['A', 'B', 'C'],
    ];
    */
    
    final public function triggerOnInstanceCreateCallback() {
        if(!isset($this->onInstanceCreate))
            return;
        
        // call the construct hook
        if($this->onInstanceCreate)
            doCall($this->onInstanceCreate['call'], $this->onInstanceCreate['args'] ?? []);
        
        // Cleanup
        unset($this->onInstanceCreate);
    }
    
    final public function setAppName($appName) {
        $this->_appName = $appName;
    }
    
    final public function getAppName() {
        return $this->_appName;
    }
    
    final public static function getModuleName() {
        static $moduleName = [];
        
        $className = static::class;
        
        if(!isset($moduleName[$className])) {
            $module = preg_replace('~^'.preg_quote(Core::system()->config['bootstrap']['applicationNameSpace']).'\\\~', '', $className, 1, $isFound);
            $moduleName[$className] = $isFound ?explode('\\', $module, 2)[0] :$className;
        }
        
        return $moduleName[$className];
    }
    
    final public static function getModulePath() {
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
    
    final public static function moduleConfig($key=null, $value=null, $merge=true) {
        static $_loadedConfig = [];
        
        $moduleName = self::getModuleName();
        $configPath = 'modules/'.$moduleName;
        
        if(!isset($_loadedConfig[$moduleName])) {
            $obj = Core::getInstance(static::class);
            $_loadedConfig[$moduleName] = Core::system()->checkAndLoadConfiguration(
                $configPath, $moduleName, $moduleName, $obj->getAppName()
            );
        }
        
        if($key)
            $configPath .= '/'.$key;
        
        if($value === null)
            return getConf($configPath);
        
        setConf($configPath, $value, $merge);
        
        return $value;
    }
    
    public static function runTimeCache($key=null, $value=null, $merge=true) {
        $obj = Core::getInstance(static::class);
        
        if($value === null) {
            if($key === null)
                return $obj->_runTimeCache;
            
            return $obj->_runTimeCache[$key] ?? General::getMultiArrayValue(explode('/', $key), $obj->_runTimeCache);
        }
        
        if($merge)
            $obj->_runTimeCache = General::arrayMergeRecursiveDistinct($obj->_runTimeCache, General::setMultiArrayValue($key, $value));
        else
            $obj->_runTimeCache = General::setMultiArrayValue($key, $value, $obj->_runTimeCache);
        
        return $value;
    }
    
    public function _($className, &$assignTo=null, $forStaticCall=false) {
        $applicationNameSpace = Core::system()->config['bootstrap']['applicationNameSpace'];
        
        if(strpos($className, $applicationNameSpace) !== 0)
            $className = $applicationNameSpace.'\\'.$className;
        
        $assignTo = Core::system()->getAppClass($this->getAppName(), $className, false);
        
        return $forStaticCall ?$className :$assignTo;
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
    }
    
    public function __set($key, $value) {
        if(isset(Core::system()->_restrictedProperties[$key]))
            throw new Exceptions\ReservedProperty("Property `{$key}` is reserved for Zamp PHP!");
        
        $this->$key = $value;
    }
}
/* END OF FILE */
