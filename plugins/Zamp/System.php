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
	public $applicationUrl;
	public $currentUrl;
	
	// Internal
	private $_internal = [
		'loadedConfigFiles' => [],
	];
	
	/**
	 * 	Run time properties
	 * 	
	 * 	[
	 * 		'{key}' => [
	 * 			'type' => 'Array|String|Object|Bool|Int',
	 * 			'data' => '{value}',
	 * 		],
	 * 	];
	 */
	public $_runTimeProperties = [];
	
	/**
	 *	restricted properties to avoid problem when loading cross application and
	 *	setting property to framework object
	 */
	public $_restrictedProperties = [
		'_runTimeProperties' => 'Array',
		'_restrictedProperties' => 'Array',
		'config' => 'Array',
		
		'request' => 'Object',
		'view' => 'Object',
		'cache' => 'Object',
		
		'rootUrl' => 'String',
		'applicationUrl' => 'String',
		'currentUrl' => 'String',
	];
	
	// Boot info
    private $_bootInfo = [
		'requested_url' => '',
		'controller' => [
			'request' => '',
			'class' => '',
			'path' => '',
		],
		'action' => '',
		'query' => [],
		'routeInfo' => [
			/*'original_url' => '',
			'rule_matched' => '',
			'rule_value' => '',
			'routed_url' => '',*/
		],
		'application' => [
			'name' => '',
			'path' => '',
			'isDefault' => true,
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
		$errorDialog = "Undefined function <font color='blue'>$funName</font> called with the following arguments";
		$errorDialog .= '<pre>'.var_export($args, true).'</pre>';
		
		throw new \Exception($errorDialog);
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
			throw new \Exception("Property <font color='red'>$name</font> can not be set due to restricted properties.");
		
		$this->_runTimeProperties[$name] = [
			'type' => ucfirst(strtolower($type)),
			'data' => $value,
		];
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
		
		if(empty($config['applications']) || (array) $config['applications'] !== $config['applications'])
            throw new \Exception('Applications not defined');
		
		$this->view = Core::getInstance(View::class);
		$this->view->setThemeName($config['view']['themeName']);
		$this->view->setFileExtension($config['view']['fileExtension']);
		
		$routeInfo = $this->checkRouterAndBuild($this->request->server('REQUEST_URI'), $config['router']);
		
		$this->updateRoutingValues($routeInfo);
		
		StopWatch::stop(__METHOD__);
		
		return $this;
	}
	
	private function _loadCoreModuleDefaultConfig() {
		$moduleConfig = $this->_bootInfo['application']['path'].'/Core/Config';
		
		$noAuto = glob($moduleConfig.'/*.noauto.php');
		$all = glob($moduleConfig.'/*.php');
		
		if($all && $noAuto)
			$configFiles = array_diff($all, $noAuto);
		elseif($all && !$noAuto)
			$configFiles = $all;
		else
			$configFiles = [];
		
		foreach($configFiles as $configFile) {
			$this->_internal['loadedConfigFiles'][$configFile] = 1;
			require_once $configFile;
		}
		
		$this->config = General::arrayMergeRecursiveDistinct($this->config, $config ?: []);
		
		if($this->config['bootstrap']['onModuleConfigLoadedCallback']) {
			doCall($this->config['bootstrap']['onModuleConfigLoadedCallback'], [
				'Core', $this->_bootInfo['application']['name'], $configFiles
			]);
		}
	}
	
	public function checkAndLoadConfiguration($confToCheck, $confFileName, $moduleName, $appName='') {
		if(!$appName)
			$appName = $this->_bootInfo['application']['name'];
		
		static $_loadedConf = [];
		
		if(isset($_loadedConf[$confToCheck]))
			return false;
		
		if(getConf($confToCheck))
			return $_loadedConf[$confToCheck] = false;
		
		$configFile = getConfFile($confFileName, $moduleName, $appName);
		
		if(isset($this->_internal['loadedConfigFiles'][$configFile]))
			return $_loadedConf[$confToCheck] = false;
		
		$this->_internal['loadedConfigFiles'][$configFile] = 1;
		require_once $configFile;
		
		$this->config = General::arrayMergeRecursiveDistinct($this->config, $config ?: []);
		
		if($this->config['bootstrap']['onModuleConfigLoadedCallback']) {
			doCall($this->config['bootstrap']['onModuleConfigLoadedCallback'], [
				$moduleName, $appName, [$configFile]
			]);
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
							throw new \Exception("Method access error reporting failed. Method name `{$handler}` not found in <font color='blue'>{$this->_bootInfo['controller']['class']}</font>");
						}
						else
							$this->showErrorPage(500);
					}
					
					$isOk = $obj->$handler($action, $isOk);
				}
				elseif($this->config['bootstrap']['isDevelopmentPhase']) {
					if($isOk == 'internalMethod')
						throw new \Exception("Method begins with `_` are considered as internal use only, and can not be accessed via routing.");
					elseif($isOk == 'blocked')
						throw new \Exception("<font color='blue'>{$this->_bootInfo['controller']['class']}->{$action}()</font> is in `_blockedMethods` list.");
					else
						throw new \Exception("<font color='blue'>{$this->_bootInfo['controller']['class']}->{$action}()</font> is not defined.");
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
	
	public function getAppClass($appName, $className, $forStaticCall=false) {
		static $_loadedAppClasses = [];
		
		$cacheKey = $appName.':'.$className;
		
		if(isset($_loadedAppClasses[$cacheKey])) {
			$instance = Core::getInstance($className);
			$instance->setAppName($appName);
			
			return $forStaticCall ?$className :$instance;
		}
		
		Core::isAvailable($className, $appName, true, true);
		
		$reflection = new \ReflectionClass($className);
		$instance = $reflection->newInstance();
		$instance->setAppName($appName);
		$instance->triggerOnInstanceCreateCallback();
		
		$_loadedAppClasses[$cacheKey] = 1;
		
		Core::setInstance($className, $instance);
		
		return $forStaticCall ?$className :$instance;
	}
	
	private function _prepareController($controller) {
		$controller = preg_split('/(?=[A-Z])/', $controller);
		
		if(!isset($controller[1]))
			$controller = ucfirst($controller[0]);
		else {
			// starting with capital letter
			if(!$controller[0])
				array_shift($controller);
			else
				$controller[0][0] = strtoupper($controller[0][0]);
			
			$controller = implode('', $controller);
		}
		
		return $controller;
	}
	
	private function _setController() {
		$controller = $this->_bootInfo['controller']['request'] ?: $this->config['bootstrap']['defaultController'];
		$action = $this->_bootInfo['action'] ?: $this->config['bootstrap']['defaultAction'];
		
		$controller = $this->_prepareController($controller);
		
		if(($application = $this->config['bootstrap']['applications'][$controller] ?? '')) {
			$this->_bootInfo['application']['name'] = $application;
			$this->_bootInfo['application']['isDefault'] = false;
			
			$controller = $this->_prepareController($action);
			$action = $this->config['bootstrap']['defaultAction'];
			
			if(!empty($this->_bootInfo['query'][0])) {
				$action = $this->_bootInfo['query'][0];
				unset($this->_bootInfo['query'][0]);
				
				$query = [];
				$next = 1;
				$skip = false;
				
				foreach($this->_bootInfo['query'] as $k => $v) {
					if($skip) {
						$query[$k] = $v;
						continue;
					}
					
					if($k === $next) {
						$query[$k-1] = $v;
						$next++;
					}
					else {
						$skip = true;
						$query[$k] = $v;
					}
				}
				
				$this->_bootInfo['query'] = $_GET = $query;
			}
		}
		elseif(($application = $this->config['bootstrap']['applications']['*'] ?? '')) {
			$this->_bootInfo['application']['name'] = $application;
			$this->_bootInfo['application']['isDefault'] = true;
		}
		
		$this->_bootInfo['controller']['request'] = $controller;
		$this->_bootInfo['action'] = strtolower($action);
		
		$this->_bootInfo['application']['path'] = PATH_DETAILS['APPLICATIONS'].($application ?'/'.$application :'');
		
		$this->_bootInfo['controller']['class'] = $this->config['bootstrap']['applicationNameSpace'].'\\Core\\'.$controller.'Controller';
		
		$mappingFile = $this->_bootInfo['application']['path'].'/classMapping.php';
		
		if(isFileExists($mappingFile, ($application ?$application.':' :'').'classMapping')) {
			$mapping = require $mappingFile;
			
			if(isset($mapping[$controller])) {
				$this->_bootInfo['controller']['class'] = $this->config['bootstrap']['applicationNameSpace'].'\\'.$mapping[$controller].'\\'.$controller.'Controller';
			}
		}
		
		try {
			$this->_bootInfo['controller']['path'] = Core::isAvailable($this->_bootInfo['controller']['class'], $application, false, true);
		}
		catch(\Exception $e) {
			if($this->config['bootstrap']['isDevelopmentPhase'])
				throw new \Exception($e->getMessage());
			
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
	
	public function checkRouterAndBuild($requestedUrl, $routerConfig = []) {
		$route = $routerConfig ?: ($this->config['bootstrap']['router'] ?? []);
		
		$this->_bootInfo['requested_url'] = $queryString = ltrim($requestedUrl, '/');
		
		if($route && (array) $route === $route) {
			foreach($route as $condition => $value) {
				if(preg_match($condition, $requestedUrl, $matches)) {
					$this->_bootInfo['routeInfo'] = [
						'original_url' => $queryString,
						'rule_matched' => $condition,
						'rule_value' => $value,
						'routed_url' => '',
					];
					
					if(preg_match_all("/#(\d+)#/", $value, $subMatches)) {
						foreach($subMatches[1] as $v)
							$value = str_replace("#$v#", $matches[$v], $value);
					}
					
					$this->_bootInfo['routeInfo']['routed_url'] = $this->_bootInfo['requested_url'] = $queryString = $value;
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
			
			$requestedUrl = [];
			
			if(!empty($this->_bootInfo['controller']['request'])) {
				$requestedUrl[] = $this->_bootInfo['controller']['request'];
				
				if(!empty($this->_bootInfo['action']))
					$requestedUrl[] = $this->_bootInfo['action'];
			}
			
			$requestedUrl[] = $this->request->getQueryUrl(true, '&', $GetQueryString);
			
			$requestedUrl = implode('/', $requestedUrl);
			
			if(!isset($GetQueryString[0]))
				$requestedUrl = preg_replace('~/\?~', '?', $requestedUrl, 1);
			
			$this->_bootInfo['requested_url'] = $requestedUrl;
		}
		
		return $this->_bootInfo;
	}
	
	public function updateRoutingValues($routingValues, $updateCurrentUrl=true) {
		$this->_bootInfo = $routingValues;
		$_GET = $routingValues['query'];
		
		$this->_setController();
		
		$this->rootUrl = $this->_getRootURL().'/';
		$this->currentUrl = $this->rootUrl.$routingValues['requested_url'];
		$this->applicationUrl = $this->rootUrl;
		
		if(!$this->_bootInfo['application']['isDefault'])
			$this->applicationUrl .= $this->_bootInfo['application']['name'].'/';
		
		$themeName = $this->view->getThemeName();
		
		$this->view->set([
			'app' => [
				'rootUrl' => $this->rootUrl,
				'applicationUrl' => $this->applicationUrl,
				'currentUrl' => $this->currentUrl,
				'assets' => [
					'js' => $this->applicationUrl."js/$themeName/",
					'css' => $this->applicationUrl."css/$themeName/",
					'images' => $this->applicationUrl."images/$themeName/",
					'libs' => $this->applicationUrl.'libs/',
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
		
		throw new \Exception($errorDialog, 16);
	}
	
    public function systemTime($format=null, $timeDiff=null) {
		$now = \DateTime::createFromFormat('U.u', number_format(microtime(true), 6, '.', ''));
		$timeDiff = (int) ($timeDiff ?? $this->config['bootstrap']['appTimeDiffFromGmt']);
		$now->modify($timeDiff.' seconds');
		
		if(!isset($format))
			return (int) $now->format('U');
		
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