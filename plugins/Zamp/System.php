<?php

namespace Zamp;

class System extends Base {
    // Application configurations
    public $config = [];
    
    // Request object
    public $request;
    
    // View object
    public $view;
    
    // URLs
    public $rootUrl;
    public $currentUrl;
    
    // Internal
    private $_internal = [
        'loadedConfigFiles' => [],
    ];
    
    /**
     *  Run time properties
     *  
     *  [
     *      '{key}' => [
     *          'type' => 'Array|String|Object|Bool|Int',
     *          'data' => '{value}',
     *      ],
     *  ];
     */
    public $_runTimeProperties = [];
    
    /**
     *  restricted properties to avoid problem setting property to framework object
     */
    public $_restrictedProperties = [
        '_runTimeProperties' => 'Array',
        '_restrictedProperties' => 'Array',
        'config' => 'Array',
        
        'request' => 'Object',
        'view' => 'Object',
        'cache' => 'Object',
        
        'rootUrl' => 'String',
        'currentUrl' => 'String',
    ];
    
    // Boot info
    private $_bootInfo = [
        'preparedUrl' => '',
        'controller' => [
            'request' => '',
            'class' => '',
            'path' => '',
        ],
        'action' => '',
        'query' => [],
        'routeInfo' => [
            /*'originalUrl' => '',
            'ruleMatched' => '',
            'ruleValue' => '',
            'routedUrl' => '',*/
        ],
        'isRouteStopped' => false,
    ];
    
    // checking initialize
    private $_initialized = 0;
    
    public function __construct() {
        $this->setErrorHandlers();
        $this->request = Core::getInstance(Request::class);
        $this->_initialized = 1;
    }
    
    public function __call($funName, $args) {
        $errorDialog = "Undefined function `$funName` called with the following arguments".NEXT_LINE;
        $errorDialog .= '<pre>'.var_export($args, true).'</pre>';
        
        throw new Exceptions\UndefinedMethod($errorDialog);
    }
    
    public function __get($name) {
        if($name == 'cache')
            return $this->_startCache();
    }
    
    private function _startCache() {
        $cache = $this->config['bootstrap']['cache'];
        return $this->cache = Core::getInstance(Cache::class."\\{$cache['driver']}", [$cache['driverConfig'], $cache['defaultTtl']]);
    }
    
    public function setProperty($name, $type, $value) {
        if(isset($this->_restrictedProperties[$name]))
            throw new Exceptions\ReservedProperty("Property `$name` can not be set due to restricted properties.");
        
        $this->_runTimeProperties[$name] = [
            'type' => ucfirst(strtolower($type)),
            'data' => $value,
        ];
    }
    
    public function getProperty($name) {
        return $this->_runTimeProperties[$name] ?? null;
    }
    
    public function bootstrap($config) {
        if($this->_initialized > 1)
            return $this;
        
        $this->_initialized = 2;
        
        StopWatch::start('Total_Runtime');
        StopWatch::start(__METHOD__);
        
        $this->config = [
            'bootstrap' => $config,
        ];
        
        if(!$this->isExtensionLoaded($config['requiredModules']))
            return $this;
        
        $this->view = Core::getInstance(View::class);
        $this->view->setThemeName($config['view']['themeName']);
        $this->view->setFileExtension($config['view']['fileExtension']);
        
        $routeInfo = $this->checkRouterAndBuild($this->request->server('REQUEST_URI'), $config['router']);
        
        $this->updateRoutingValues($routeInfo);
        
        StopWatch::stop(__METHOD__);
        
        return $this;
    }
    
    private function _loadCoreModuleDefaultConfig() {
        $moduleConfig = PATH_DETAILS['APPLICATION'].'/Core/Config';
        
        $noAuto = glob($moduleConfig.'/*.noauto.php');
        $all = glob($moduleConfig.'/*.php');
        
        if($all && $noAuto)
            $configFiles = array_diff($all, $noAuto);
        elseif($all && !$noAuto)
            $configFiles = $all;
        else
            $configFiles = [];
        
        $loadedConfigs = [];
        
        foreach($configFiles as $configFile) {
            $config = require_once $configFile;
            $config = General::setMultiArrayValue(explode('/', $configPath), $config);
            
            $this->_internal['loadedConfigFiles'][$configFile] = $configPath;
            $loadedConfigs[$configFile] = $configPath;
            
            $this->config = General::arrayMergeRecursiveDistinct($this->config, $config);
        }
        
        if($this->config['bootstrap']['onModuleConfigLoadedCallback'])
            doCall($this->config['bootstrap']['onModuleConfigLoadedCallback'], ['Core', '*', $loadedConfigs]);
    }
    
    public function checkAndLoadConfiguration($confToCheck, $confFileName, $moduleName) {
        static $_loadedConf = [];
        
        if(isset($_loadedConf[$confToCheck]))
            return false;
        
        if(getConf($confToCheck))
            return $_loadedConf[$confToCheck] = false;
        
        $configFile = getConfFile($confFileName, $moduleName);
        
        if(isset($this->_internal['loadedConfigFiles'][$configFile]))
            return $_loadedConf[$confToCheck] = false;
        
        $config = require_once $configFile;
        $config = General::setMultiArrayValue(explode('/', $configPath), $config);
        
        $this->_internal['loadedConfigFiles'][$configFile] = $configPath;
        
        $this->config = General::arrayMergeRecursiveDistinct($this->config, $config);
        
        if($this->config['bootstrap']['onModuleConfigLoadedCallback']) {
            doCall($this->config['bootstrap']['onModuleConfigLoadedCallback'], [$moduleName, $confToCheck, [
                $configFile => $configPath
            ]]);
        }
        
        return $_loadedConf[$confToCheck] = true;
    }
    
    public function run() {
        if($this->_initialized > 2)
            return $this;
        
        $this->_initialized = 3;
        
        $this->_checkMaintananceSettings();
        
        StopWatch::start(__METHOD__);
        
        // loading Core module configurations
        $this->_loadCoreModuleDefaultConfig();
        
        // Call pre process callbacks
        if($this->config['bootstrap']['onBeforeProcessCallbacks']) {
            foreach($this->config['bootstrap']['onBeforeProcessCallbacks'] as $callback) {
                if(doCall($callback) === false)
                    break;
            }
        }
        
        // Call requested Controller if route is NOT stopped
        if(empty($this->_bootInfo['isRouteStopped'])) {
            $obj = Core::getInstance($this->_bootInfo['controller']['class']);
            
            $action = $this->_bootInfo['action'];
            
            if($action[0] == '_')
                $isOk = 'internalMethod';
            elseif($blockedActions = $obj->getBlockedMethods()) {
                foreach($blockedActions as $blocked) {
                    if($action == strtolower($blocked)) {
                        $isOk = 'blocked';
                        break;
                    }
                }
            }
            
            if(!isset($isOk))
                $isOk = method_exists($obj, $action) ?: 'undefined';
            
            if($isOk !== true) {
                if($handler = $obj->getMethodErrorHandler()) {
                    if(!method_exists($obj, $handler)) {
                        if($this->config['bootstrap']['isDevelopmentPhase']) {
                            throw new Exceptions\UndefinedMethod("Method access error reporting failed. Method name `{$handler}` not found in `{$this->_bootInfo['controller']['class']}`");
                        }
                        else
                            $this->showErrorPage(500);
                    }
                    
                    $isOk = $obj->$handler($action, $isOk);
                }
                elseif($this->config['bootstrap']['isDevelopmentPhase']) {
                    if($isOk == 'internalMethod') {
                        throw new Exceptions\RestrictedAccess("Method begins with `_` are considered as internal use only, and can not be accessed via routing.", 1);
                    }
                    elseif($isOk == 'blocked') {
                        throw new Exceptions\RestrictedAccess("`{$this->_bootInfo['controller']['class']}->{$action}()` is in `_blockedMethods` list.", 2);
                    }
                    else {
                        throw new Exceptions\UndefinedMethod("`{$this->_bootInfo['controller']['class']}->{$action}()` is not defined.");
                    }
                }
                else {
                    if($callback = $this->config['bootstrap']['routing404Callback'])
                        $isOk = doCall($callback, ['methodNotFound', $isOk]);
                    
                    if($isOk !== true)
                        $this->showErrorPage(404);
                }
            }
            
            $this->view->actionFile($this->_bootInfo['controller']['request'], $this->_bootInfo['action']);
            
            try {
                if($isOk === true) {
                    // Callback may change the action
                    $obj->{$this->_bootInfo['action']}();
                }
            }
            catch(\Error $e) {
                ErrorHandler::exceptionHandler($e);
            }
            
            $this->view->actionFileCheck();
        }
        
        // Call post process callbacks
        if($this->config['bootstrap']['onAfterProcessCallbacks']) {
            foreach($this->config['bootstrap']['onAfterProcessCallbacks'] as $callback) {
                if(doCall($callback) === false)
                    break;
            }
        }
        
        StopWatch::stop(__METHOD__);
        StopWatch::stop('Total_Runtime');
        
        $this->view->render();
    }
    
    private function _checkMaintananceSettings() {
        $settings = $this->config['bootstrap']['maintananceSettings'];
        
        if(empty($settings['isActive']))
            return;
        
        if($callback = $settings['exclusiveAccessCallback']) {
            if(doCall($callback) === true)
                return;
        }
        
        if($callback = $settings['viewRenderCallback'])
            $message = doCall($callback);
        
        General::setHeader(503);
        
        if($message !== false) {
            $message = $message ?: 'Server under maintanace. We sincerely regret the inconvenience caused.';
            require_once PATH_DETAILS['PLUGINS']."/Zamp/Exceptions/templates/maintanance_mode.html.php";
        }
        
        cleanExit();
    }
    
    public function className($name) {
        $name = preg_split('/(?=[A-Z])/', $name);
        
        if(!isset($name[1]))
            $name = ucfirst($name[0]);
        else {
            // starting with capital letter
            if(!$name[0])
                array_shift($name);
            else
                $name[0][0] = strtoupper($name[0][0]);
            
            $name = implode('', $name);
        }
        
        return $name;
    }
    
    private function _setController() {
        $controller = $this->_bootInfo['controller']['request'] ?: $this->config['bootstrap']['defaultController'];
        $action = $this->_bootInfo['action'] ?: $this->config['bootstrap']['defaultAction'];
        
        $controller = $this->className($controller);
        
        $this->_bootInfo['controller']['request'] = $controller;
        $this->_bootInfo['action'] = strtolower($action);
        
        $this->_bootInfo['controller']['class'] = $this->config['bootstrap']['applicationNameSpace'].'\\Core\\'.$controller.'Controller';
        
        $mappingFile = PATH_DETAILS['APPLICATION'].'/classMapping.php';
        
        if(isFileExists($mappingFile, 'classMapping')) {
            $mapping = require_once $mappingFile;
            
            if(isset($mapping[$controller])) {
                $this->_bootInfo['controller']['class'] = $this->config['bootstrap']['applicationNameSpace'].'\\'.$mapping[$controller].'\\'.$controller.'Controller';
            }
        }
        
        loadController:
        
        try {
            $info = Core::isAvailable($this->_bootInfo['controller']['class'], false, true);
            $this->_bootInfo['controller']['path'] = $info['filePath'];
        }
        catch(\Exception $e) {
            $newController = $this->config['bootstrap']['applicationNameSpace'].'\\'.$controller.'\\'.$controller.'Controller';
            
            if($newController != $this->_bootInfo['controller']['class']) {
                $this->_bootInfo['controller']['class'] = $newController;
                goto loadController;
            }
            
            if($this->config['bootstrap']['isDevelopmentPhase'])
                throw new Exceptions\ControllerNotFound($e->getMessage());
            
            $showErrorPage = true;
            
            if($callback = $this->config['bootstrap']['routing404Callback']) {
                if(doCall($callback, ['controllerNotFound', 'controllerFileNotFound']) === true)
                    $showErrorPage = false;
            }
            
            if($showErrorPage)
                $this->showErrorPage(404);
        }
    }
    
    public function showErrorPage($code, $file=null) {
        General::setHeader($code);
        require_once $file ?? PATH_DETAILS['PLUGINS']."/Zamp/Exceptions/templates/error{$code}.html.php";
        cleanExit();
    }
    
    public function checkRouterAndBuild($preparedUrl, $routerConfig = []) {
        $route = $routerConfig ?: ($this->config['bootstrap']['router'] ?? []);
        
        $this->_bootInfo['preparedUrl'] = $queryString = ltrim($preparedUrl, '/');
        
        if($route && (array) $route === $route) {
            foreach($route as $condition => $value) {
                if(preg_match($condition, $this->_bootInfo['preparedUrl'], $matches)) {
                    $this->_bootInfo['routeInfo'] = [
                        'originalUrl' => $queryString,
                        'ruleMatched' => $condition,
                        'ruleValue' => $value,
                        'routedUrl' => '',
                    ];
                    
                    if(preg_match_all("/#(\d+)#/", $value, $subMatches)) {
                        foreach($subMatches[1] as $v)
                            $value = str_replace("#$v#", $matches[$v], $value);
                    }
                    
                    $this->_bootInfo['routeInfo']['routedUrl'] = $this->_bootInfo['preparedUrl'] = $queryString = $value;
                    break;
                }
            }
        }
        
        $temp = explode("?", $queryString);
        $queryString = explode('/', $temp[0], 3);
        
        $this->_bootInfo['controller']['request'] = $queryString[0];
        
        if(!empty($queryString[1]))
            $this->_bootInfo['action'] = $queryString[1];
        
        if(isset($temp[1]))
            parse_str($temp[1], $GetQueryString);
        
        if(isset($queryString[2])) {
            $temp2 = explode('/', $queryString[2]);
            
            foreach($temp2 as $temp3) {
                if($temp3)
                    $GetQueryString[] = $temp3;
            }
        }
        
        if(isset($GetQueryString)) {
            ksort($GetQueryString, SORT_STRING);
            $this->_bootInfo['query'] = $GetQueryString;
            
            $preparedUrl = [];
            
            if(!empty($this->_bootInfo['controller']['request'])) {
                $preparedUrl[] = $this->_bootInfo['controller']['request'];
                
                if(!empty($this->_bootInfo['action']))
                    $preparedUrl[] = $this->_bootInfo['action'];
            }
            
            $preparedUrl[] = $this->request->getQueryUrl(true, '&', $GetQueryString);
            
            $preparedUrl = implode('/', $preparedUrl);
            
            if(!isset($GetQueryString[0]))
                $preparedUrl = preg_replace('~/\?~', '?', $preparedUrl, 1);
            
            $this->_bootInfo['preparedUrl'] = $preparedUrl;
        }
        
        return $this->_bootInfo;
    }
    
    public function urlPath() {
        return $this->_bootInfo['routeInfo']['originalUrl'] ?? $this->_bootInfo['preparedUrl'];
    }
    
    public function updateRoutingValues($routingValues, $updateCurrentUrl=true) {
        $this->_bootInfo = $routingValues;
        $_GET = $routingValues['query'];
        
        $this->_setController();
        
        $this->rootUrl = $this->_getRootURL().'/';
        $this->currentUrl = $this->rootUrl.$this->urlPath();
        
        $themeName = $this->view->getThemeName();
        
        $this->view->set([
            'app' => [
                'rootUrl' => $this->rootUrl,
                'currentUrl' => $this->currentUrl,
                'assets' => [
                    'js' => $this->rootUrl."js/$themeName/",
                    'css' => $this->rootUrl."css/$themeName/",
                    'images' => $this->rootUrl."images/$themeName/",
                    'libs' => $this->rootUrl.'libs/',
                ],
            ],
        ], true);
    }
    
    public function bootInfo($key=null) {
        return $key ?($this->_bootInfo[$key] ?? null) :$this->_bootInfo;
    }
    
    public function bootInfoSet($key, $value) {
        $this->_bootInfo[$key] = $value;
    }
    
    private function _getRootURL() {
        $host = $this->request->server('HTTP_HOST') ?:'';
        
        if(!$serverPort = strstr($host, ':'))
            $serverPort = '';
        elseif($serverPort == ':80' || $serverPort == ':443')
            $serverPort = '';
        
        $protocol = General::isSslConnection() ?'https://' :'http://';
        
        if($serverPort)
            $url = $protocol.str_replace($serverPort, '', $host).$serverPort;
        else
            $url = $protocol.$host;
        
        return $url;
    }
    
    public function stopRouting() {
        $this->_bootInfo['isRouteStopped'] = true;
    }
    
    public function isExtensionLoaded($modules) {
        if(empty($this->config['bootstrap']['isDevelopmentPhase']) || empty($modules))
            return true;
        
        $loaded = $notLoaded = $checked = [];
        
        foreach($modules as $module) {
            if(extension_loaded($module)) {
                $loaded[$module] = true;
                $checked[$module] = "<font color='green'><b>PASSED</b></font>";
            }
            else {
                $notLoaded[] = $module;
                $checked[$module] = "<font color='red'><b>FAILED</b></font>";
            }
        }
        
        $total = count($modules);
        
        if($total == count($loaded))
            return true;
        
        $errorDialog = "<font color='red' size='4'>System Requirements Failed!</font>
                        <br/>
                        The following failed modules must be enabled.
                        <table cellpadding='3'>";
        foreach($checked as $key => $value)
            $errorDialog .= "<tr><td width='50'>$key</td><td>=></td><td>$value</td></tr>";
        $errorDialog .= "
                        </table>";
        
        throw new Exceptions\Bootstrap($errorDialog);
    }
    
    /**
     *  \Zamp()->systemTime('iso-gmt'); // 2020-04-09T06:41:26.977Z
     *  \Zamp()->systemTime('micro-seconds'); // 1586414584686038
     *  \Zamp()->systemTime('mili-seconds'); // 1586414584686
     *  \Zamp()->systemTime(); // 1586414584
     *  \Zamp()->systemTime('Y-m-d'); // 2020-04-09
     *  \Zamp()->systemTime(true); // \DateTime
     *  \Zamp()->systemTime('iso-gmt', 0, '-P2M'); // 2020-02-09T06:41:26.977Z
     *  \Zamp()->systemTime('iso-gmt', 0, 'P2M'); // 2020-06-09T06:41:26.977Z
     */
    public function systemTime($format=null, $timeDiff=null, $interval=null) {
        $now = \DateTime::createFromFormat('U.u', number_format(microtime(true), 6, '.', ''));
        
        if($interval) {
            $intervalType = 'add';
            
            if($interval[0] == '-') {
                $interval = substr($interval, 1);
                $intervalType = 'sub';
            }
            
            $now->$intervalType(new \DateInterval($interval));
        }
        
        if($format === 'iso-gmt')
            return substr($now->format('Y-m-d\TH:i:s.u'), 0, -3).'Z';
        
        if($timeDiff = (int) ($timeDiff ?? $this->config['bootstrap']['appTimeDiffFromGmt']))
            $now->modify($timeDiff.' seconds');
        
        if(!isset($format))
            return (int) $now->format('U');
        
        elseif($format === true)
            return $now;
        
        elseif($format === 'micro-seconds')
            return (int) $now->format('Uu');
        
        elseif($format === 'mili-seconds')
            return (int) substr($now->format('Uu'), 0, -3);
        
        else
            return $now->format($format);
    }
    
    public function setErrorHandlers() {
        set_error_handler([ErrorHandler::class, 'errorHandler'], error_reporting());
        set_exception_handler([ErrorHandler::class, 'exceptionHandler']);
    }
}
/* END OF FILE */
