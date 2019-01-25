<?php

namespace Zamp;

if(phpversion() < '7.3.0')
    exit("Your PHP version must be 7.3.0 or higher. Current version: " . phpversion());

// Zamp PHP version
const VERSION = '6.1.0';

// Define next line character
define(__NAMESPACE__.'\NEXT_LINE', (
    php_sapi_name() === 'cli' || defined('STDIN') || isset($_ENV['SHELL'])) ?PHP_EOL :'<br/>'
);

/**
 *  Define the PATH_DETAILS constant
 * 
 *  [
 *      'PUBLIC' => 'path to public folder',
 *      'PROJECT' => 'path to project main folder',
 *      'APPLICATION' => 'path to application folder',
 *      'PLUGINS' => 'path to plugins folder',
 *      'TEMP' => 'path to temporary folder',
 *  ];
 */
if(!defined('PATH_DETAILS'))
    exit('Define the constant `PATH_DETAILS` in index.php file!');

require_once PATH_DETAILS['PLUGINS'].'/Zamp/Base.php';
require_once PATH_DETAILS['PLUGINS'].'/Zamp/System.php';
require_once PATH_DETAILS['PLUGINS'].'/Zamp/General.php';
require_once PATH_DETAILS['PLUGINS'].'/Zamp/ErrorHandler.php';
require_once PATH_DETAILS['PLUGINS'].'/Zamp/StopWatch.php';
require_once PATH_DETAILS['PLUGINS'].'/Zamp/Request.php';
require_once PATH_DETAILS['PLUGINS'].'/Zamp/Security.php';
require_once PATH_DETAILS['PLUGINS'].'/Zamp/View.php';
require_once PATH_DETAILS['PLUGINS'].'/Zamp/Application.php';

final class Core {
    // Initiated objects
    private static $_instances = [];
    
    // Class alias
    private static $_aliases = [];
    
    // If vendor having their own auto loader, add them in Zamp auto loader exclude list
    private static $_autoloaderSkipList = [];
    
    // List of vendors and its loading path
    private static $_vendors;
    
    // Callbacks to call before exit
    private static $_cleanExitCallbacks = [];
    
    /**
     *  Callback to call before URL redirect
     *  
     *  callbackCalledWith(String $url): void
     */
    private static $_headerRedirectCallback = false;
    
    final private function __construct() {}
    
    // Return System class
    public static function system() {
        return self::$_instances[System::class];
    }
    
    // Add NameSpace to skip Zamp Autoloader
    public static function skipAutoLoaderFor($nameSpace) {
        self::$_autoloaderSkipList[$nameSpace] = 1;
    }
    
    // Check if the class name is in skip list
    public static function isInAutoLoaderSkipList($className) {
        foreach(self::$_autoloaderSkipList as $nameSpace => $status) {
            if(strpos($className, $nameSpace) === 0)
                return true;
        }
        
        return false;
    }
    
    /**
     *  Register your `doCall` compatible callback functions
     *  which will be called with exit parameter.
     *    
     *  NOTE: If your callback return `false` then following registered callbacks not called.
     */
    public static function cleanExitCallbackSet($name, $callback) {
        self::$_cleanExitCallbacks[$name] = $callback;
    }
    
    // Get exit callbacks
    public static function cleanExitCallbackGet($name=null) {
        return $name ?(self::$_cleanExitCallbacks[$name] ?? null) :self::$_cleanExitCallbacks;
    }
    
    // Set callback to call when do URL redirect
    public static function headerRedirectCallbackSet($callback) {
        self::$_headerRedirectCallback = $callback;
    }
    
    // Get registered callback
    public static function headerRedirectCallbackGet() {
        return self::$_headerRedirectCallback;
    }
    
    // Set vendors folder paths
    public static function setVendorPaths($path, $prefix='*') {
        if(!isset(self::$_vendors[$prefix]))
            self::$_vendors[$prefix] = [];
        
        if((array) $path === $path)
            self::$_vendors[$prefix] = array_merge(self::$_vendors[$prefix], $path);
        else
            self::$_vendors[$prefix][$path] = true;
    }
    
    // Get vendors path settings
    public static function getVendorsPath() {
        return self::$_vendors;
    }
    
    // Return the file path for the given class
    public static function getVendorPath($className, $cacheKey='') {
        if(!preg_match("/[_\\\]/", $className))
            return false;
        
        foreach(self::$_vendors as $prefix => $paths) {
            if($prefix == '*')
                continue;
            
            $len = strlen($prefix);
            
            if(substr($className, 0, $len) != $prefix)
                continue;
            
            $file = substr($className, $len);
            $file = preg_replace("/[_\\\]/", '/', $file).'.php';
            
            foreach($paths as $path => $isActive) {
                if(!$isActive)
                    continue;
                
                $filename = $path.$file;
                $filename = isFileExists($filename, $cacheKey, true);
                
                if($filename)
                    return $filename;
            }
        }
        
        if(empty(self::$_vendors['*']))
            return false;
        
        $file = preg_replace("/[_\\\]/", '/', $className).'.php';
        
        foreach(self::$_vendors['*'] as $path => $isActive) {
            if(!$isActive)
                continue;
            
            $filename = $path.$file;
            $filename = isFileExists($filename, $cacheKey, true);
            
            if($filename)
                return $filename;
        }
        
        return false;
    }
    
    private static function _detectAlias($info) {
        if(!$info['fileLoaded'] || $info['classAlias'] || class_exists($info['className'], false))
            return $info;
        
        $isAppClass = $info['isAppClass'] ?: strpos($info['className'], self::system()->config['bootstrap']['applicationNameSpace'].'\\') === 0;
        
        if(!$isAppClass)
            return $info;
        
        $className = explode('\\', $info['className']);
        $class = array_pop($className);
        
        $info['classAlias'] = $info['className'];
        
        if($class != 'Controller')
            $info['className'] = $info['className'].'\\'.$class;
        else {
            $module = array_pop($className);
            $info['className'] = implode('\\', $className).'\\'.$module.'\\'.$module.$class;
        }
        
        return $info;
    }
    
    // Check the class file available or not
    public static function isAvailable($className, $includeFile=true, $showException=false) {
        $return = [
            'className' => $className,
            'classAlias' => null,
            'filePath' => null,
            'isAppClass' => false,
            'fileLoaded' => false,
        ];
        
        if($filename = isFileExists($className, true)) {
            $return['filePath'] = $filename;
            
            if($includeFile) {
                $return['fileLoaded'] = true;
                require_once $filename;
            }
            
            return self::_detectAlias($return);
        }
        
        $isFileExists = false;
        
        if($filename = self::getVendorPath($className, $className))
            $isFileExists = true;
        
        $_className = preg_replace('~^'.preg_quote(self::system()->config['bootstrap']['applicationNameSpace']).'\\\~', '', $className, 1, $isApplication);
        
        if($isApplication) {
            $return['isAppClass'] = true;
            
            $filename = PATH_DETAILS['APPLICATION'];
            
            $_className = explode('\\', $_className);
            
            $file = array_pop($_className);
            
            if($_className) {
                $module = array_shift($_className);
                
                if($file == 'Controller')
                    $file = $module.$file;
            }
            else {
                $module = $file;
                $return['classAlias'] = $return['className'];
                $return['className'] .= '\\'.$file;
            }
            
            if($_className)
                $file = implode('/', $_className).'/'.$file;
            elseif(substr($file, -10) == 'Controller')
                $file = 'Controller/'.$file;
            else
                $file = 'Model/'.$file;
            
            $filename .= "/$module/$file.php";
        }
        
        if($isFileExists || isFileExists($filename, $className, true)) {
            $return['filePath'] = $filename;
            
            if($includeFile) {
                $return['fileLoaded'] = true;
                require_once $filename;
            }
            
            return self::_detectAlias($return);
        }
        elseif(!$showException)
            return [];
        else
            throw new Exceptions\FileNotFound("File `$filename` Not Found!");
    }
    
    public static function getInstance($className, $args=[], $reload=false) {
        if(isset(self::$_aliases[$className]))
            $className = self::$_aliases[$className];
        
        if(!$reload && isset(self::$_instances[$className]))
            return self::$_instances[$className];
        
        $info = [];
        
        if(!class_exists($className, false)) {
            $info = self::isAvailable($className, false, true);
            require_once $info['filePath'];
            $className = $info['className'];
        }
        
        $reflection = new \ReflectionClass($className);
        
        if($reflection->getConstructor())
            $instance = $reflection->newInstanceArgs($args);
        else
            $instance = $reflection->newInstance();
        
        self::$_instances[$className] =& $instance;
        
        if($info && $info['classAlias'])
            self::$_aliases[$info['classAlias']] = $className;
        
        return $instance;
    }
    
    public static function setInstance($className, &$instance) {
        self::$_instances[$className] =& $instance;
    }
    
    public static function setAlias($className, $alias) {
        self::$_aliases[$alias] = $className;
    }
    
    public static function allInstances() {
        return self::$_instances;
    }
    
    public static function allAliases() {
        return self::$_aliases;
    }
    
    final public function __clone() {
        trigger_error('Cannot clone instance of Singleton pattern', E_USER_ERROR);
    }
}

// Setting Zamp path
Core::setVendorPaths(PATH_DETAILS['PLUGINS'].'/Zamp', 'Zamp');

// Registering an autoloader
spl_autoload_register(function($className) {
    if(
        !Core::isInAutoLoaderSkipList($className)
            &&
        ($info = Core::isAvailable($className, true, true))
    ) {
        if($info['classAlias'])
            class_alias($info['className'], $info['classAlias'], false);
    }
});

// replacement for `exit` command
function cleanExit($param=null) {
    $callbacks = Core::cleanExitCallbackGet();
    
    foreach($callbacks as $callback) {
        if(doCall($callback, [$param]) === false)
            exit($param);
    }
    
    exit($param);
}

/**
 *  Call a callback function/method
 *  
 *  Callback can be {class-name}/{method-name}:{access-type}
 *  
 *  Example 1: Zamp\doCall(modules\Member\Controller::class.'/register');
 *      => modules\Member\MemberController->register();
 *  
 *  Example 2: Zamp\doCall(modules\Member\Verification::class.'/check:static');
 *      => modules\Member\Verification::check();
 *  
 *  Example 3: Zamp\doCall(modules\Member\MemberController::class.'/register', [
 *                  'name' => 'Mathan',
 *                  'email' => 'phpmathan@gmail.com',
 *             ]);
 *      => modules\Member\MemberController->register([
*               'name' => 'Mathan',
 *              'email' => 'phpmathan@gmail.com',
 *         ]);
 *  
 *  Example 4: Zamp\doCall('myfunction', ['param1', 'param2']);
 *  
 *  Example 5: Zamp\doCall([modules\Member\Controller::class, 'isLogged']) - called as modules\Member\MemberController::isLogged()
 *  
 *  Example 6: Zamp\doCall([
 *      modules\Member\Controller::obj(),
 *      'isLogged'
 *  ]); - called as modules\Member\MemberController->isLogged()
 *  
 *  Example 7: $anonymousFunction = function($p1, $p2) {}; Zamp\doCall($anonymousFunction, [1, 2]);
 */
function doCall($callback, $params=[]) {
    if(gettype($callback) !== 'string') {
        if(is_callable($callback, true)) {
            if($params && (array) $params === $params)
                return call_user_func_array($callback, $params);
            
            return call_user_func($callback);
        }
        
        throw new Exceptions\Callback("First parameter is not valid in Zamp\doCall() function call.", 1);
    }
    
    $callback = explode('/', trim($callback, '/ '));
    $weight = count($callback);
    
    if($weight > 2)
        throw new Exceptions\Callback("Invalid Callback/Function/Method `$callback` parsed in Zamp\doCall() function!", 2);
    
    $className = array_shift($callback);
    $functionName = array_shift($callback);
    
    if($weight == 2) {
        $functionName = explode(':static', $functionName);
        
        if(isset($functionName[1]))
            $instance = $className;
        else
            $instance = Core::getInstance($className);
        
        $functionName = $functionName[0];
    }
    
    if($weight > 1)
        $functionName = [$instance, $functionName];
    
    if($params && (array) $params === $params)
        return call_user_func_array($functionName, $params);
    
    return call_user_func($functionName);
}

// Redirect to URL
function urlRedirect($url, $placeHolders=[]) {
    $url = replacePlaceHolders($url, $placeHolders);
    $url = str_replace(["\r", "\n"], '', $url);
    
    if(!preg_match('~^https?\://~i', $url))
        throw new \Exception('Invalid URL.');
    
    if($callback = Core::headerRedirectCallbackGet())
        doCall($callback, [$url]);
    
    if(!headers_sent())
        header("Location: $url");
    else
        echo "<html><head></head><body><script type='text/javascript'>document.location.href='$url';</script></body></html>";
    
    cleanExit();
}

// Replace URL and custom placeholders
function replacePlaceHolders($str, $placeHolders=[]) {
    $system = Core::system();
    
    $placeHolders = General::arrayMergeRecursiveDistinct([
        '#ROOT_URL#' => $system->rootUrl,
        '#CURRENT_URL#' => $system->currentUrl,
    ], $placeHolders);
    
    return str_replace(array_keys($placeHolders), $placeHolders, $str);
}

// Function used to check the file existence and store the result in file cache
function isFileExists($input1, $input2='', $forceCheck=false, $noCacheSave=false) {
    if($input2 === true) {
        $file = false;
        $cacheKey = $input1;
    }
    else {
        $file = str_replace('//', '/', $input1);
        $cacheKey = $input2;
    }
    
    if(!$cacheKey)
        $cacheKey = $file;
    
    if(!$cacheKey)
        return false;
    
    static $filesCache = null;
    
    if($filesCache === null) {
        $filesCache = [];
        
        $cacheFile = PATH_DETAILS['TEMP'].'/cache/zamp_file_presence_cache.php';
        
        if(file_exists($cacheFile)) {
            $filesCache = include $cacheFile;
            
            if((array) $filesCache !== $filesCache)
                $filesCache = [];
        }
    }
    
    if(!$forceCheck && isset($filesCache[$cacheKey])) {
        if($filesCache[$cacheKey] && $cacheKey == $file)
            return $file;
        else
            return $filesCache[$cacheKey];
    }
    
    if(!$file)
        return false;
    elseif(file_exists($file))
        $filesCache[$cacheKey] = $cacheKey == $file ?:$file;
    else {
        $file = false;
        $filesCache[$cacheKey] = $file;
    }
    
    if($noCacheSave && !$file)
        return $file;
    elseif(!isset($cacheFile))
        $cacheFile = PATH_DETAILS['TEMP'].'/cache/zamp_file_presence_cache.php';
    
    file_put_contents($cacheFile, "<?php\n// ".date('r')."\nreturn ".var_export($filesCache, true).";\n", LOCK_EX);
    
    General::invalidate($cacheFile);
    
    return $file;
}

// Get environment values defined in `.env.php` file under PATH_DETAILS['PROJECT']
function env($key) {
    static $env;
    
    if($env === null) {
        $file = PATH_DETAILS['PROJECT'].'/.env.php';
        $env = isFileExists($file, 'env_values') ?require_once $file :[];
    }
    
    return General::getMultiArrayValue(explode('.', $key), $env);
}

// Get configuration array or value
function getConf($key='', $configs=null) {
    if(!isset($configs))
        $configs = Core::system()->config;
    
    return $key ?General::getMultiArrayValue(explode('/', $key), $configs) :$configs;
}

// Set or modify configurations
function setConf($key='', $value, $merge=true, $customConfig=null) {
    $key = $key ?explode('/', $key) :[];
    $configs = $customConfig ?? Core::system()->config;
    
    if($merge) {
        $configs = General::arrayMergeRecursiveDistinct($configs,
            General::setMultiArrayValue($key, $value)
        );
    }
    elseif($key) {
        if(!isset($key[1]))
            $configs[$key[0]] = $value;
        else
            $configs = General::setMultiArrayValue($key, $value, $configs);
    }
    else
        $configs = $value;
    
    if(isset($customConfig))
        return $configs;
    
    Core::system()->config = $configs;
    return $value;
}

// Get module's configuration file path
function getConfFile($confFileName, $moduleName) {
    static $checkedPaths = [];
    
    $checkKey = $moduleName.':'.$confFileName;
    
    if(!isset($checkedPaths[$checkKey]))
        $checkedPaths[$checkKey] = [];
    
    $cacheKey = $moduleName.':'.$confFileName;
    
    if($confFile = isFileExists($cacheKey, true))
        return $confFile;
    
    $confFile = PATH_DETAILS['APPLICATION'].'/'.Core::system()->config['bootstrap']['configFirstCheckUnder'].'/Config/'.$confFileName.'.noauto.php';
    $checkedPaths[$checkKey][$confFile] = 1;
    
    if($filePath = isFileExists($confFile, $cacheKey))
        return $filePath;
    
    $confFile = PATH_DETAILS['APPLICATION'].'/'.$moduleName.'/Config/'.$confFileName.'.noauto.php';
    
    if(!isset($checkedPaths[$checkKey][$confFile])) {
        $checkedPaths[$checkKey][$confFile] = 1;
        
        if($filePath = isFileExists($confFile, $cacheKey, true))
            return $filePath;
    }
    
    throw new Exceptions\FileNotFound('Configuration file not found in any of the following path.'.NEXT_LINE.'- '.implode(NEXT_LINE.'- ', array_keys($checkedPaths[$checkKey])));
}

function setSession($key, $value, $unsetBeforeSet=false) {
    if(!Session::isStarted())
        throw new Exceptions\Session('Session is not yet started!', 1);
    
    $handle = function() use ($key, $value, $unsetBeforeSet) {
        $key = explode('/', $key);
        
        if($unsetBeforeSet)
            General::unsetMultiArrayValue($key, $_SESSION);
        
        $_SESSION = General::arrayMergeRecursiveDistinct($_SESSION, General::setMultiArrayValue($key, $value));
    };
    
    if(Session::isClosed()) {
        Session::reOpen();
        $handle();
        Session::writeClose();
    }
    else
        $handle();
}

function getSession($key='') {
    if(!Session::isStarted())
        throw new Exceptions\Session('Session is not yet started!', 1);
    
    return $key ?General::getMultiArrayValue(explode('/', $key), $_SESSION) :$_SESSION;
}

function deleteSession($key='') {
    if(!Session::isStarted())
        throw new Exceptions\Session('Session is not yet started!', 1);
    
    $handle = function() use ($key) {
        if($key)
            return General::unsetMultiArrayValue(explode('/', $key), $_SESSION);
        
        $_SESSION = [];
        $config = Session::profileInfo()['config'];
        
        if(!$config['isInternalOnly'] && !headers_sent()) {
            setcookie(
                $config['sessionName'], '', time() - 604800,
                $config["cookiePath"], $config["cookieDomain"], $config["cookieSecure"], $config["cookieHttpOnly"]
            );
        }
        
        if($key === false) {
            unset($_SESSION);
            session_destroy();
            
            return true;
        }
    };
    
    if(Session::isClosed()) {
        Session::reOpen();
        
        if($handle() !== true)
            Session::writeClose();
    }
    else
        $handle();
}
/* END OF FILE */