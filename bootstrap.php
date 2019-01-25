<?php

/**
 *  For explanation, assume you are in `Api` module and it namespace is `modules/Api`
 *  
 *  - Controller or Model in the SAME module can be accessed directly using $this
 *      $this->ApiController->getRequest() => this will resolve to `{APP}/Api/Controller/ApiController.php`
 *      $this->ApiHelper->checkLimits() => this will resolve to `{APP}/Api/Model/ApiHelper.php`
 * 
 *  - Controller or Model in the SAME module can be accessed directly in static method
 *      ApiController::getRequest() => this will resolve to `{APP}/Api/Controller/ApiController.php`
 *      ApiHelper::checkLimits() => this will resolve to `{APP}/Api/Model/ApiHelper.php`
 * 
 *  - If model class name same as module name, then class name can be ignored in namespace
 *      \modules\Api\Api::getToken() which can be simply \modules\Api::getToken()
 *      \module\Api\Api::obj()->getToken() which can be simply \modules\Api::obj()->getToken()
 * 
 *  - If controller class name same as module name, then class name can be just `Controller` in namespace
 *      \modules\Api\ApiController::getToken() which can be simply \modules\Api\Controller::getToken()
 *      \module\Api\ApiController::obj()->getToken() which can be simply \modules\Api\Controller::obj()->getToken()
 */
$bootstrap = [
    // Is this development phase. if set to true then error/debug messages will be displayed.
    'isDevelopmentPhase' => true,
    
    // Maintanace settings
    'maintananceSettings' => [
        // Is maintanace active
        'isActive' => false,
        
        /**
         *  Show your custom maintanace page and return `false` otherwise return 
         *  string to show in Zamp's default maintanace page screen.
         */
        'viewRenderCallback' => function() {
            header('Retry-After: 3600');
            return 'Server under maintanace. Come back after an hour.';
        },
        
        /**
         *  When maintanace mode is active, you may want to allow the access to specific peoples like
         *  developers, testing team etc.,
         *  
         *  This callback will be called only when maintainance is active, and if it return as `true` then access allowed.
         */
        'exclusiveAccessCallback' => function() {
            $request = \Zamp\Core::system()->request;
            $accessKey = 'ZampPHP';
            
            if($request->getParam('accessKey') == $accessKey) {
                setcookie('accessKey', $accessKey, time()+7200, '/');
                return true;
            }
            
            if($request->cookie('accessKey') == $accessKey)
                return true;
        },
    ],
    
    // Set the required php modules for your Application
    'requiredModules' => [
        // Zamp required modules
        'session', 'json', 'date', 'openssl',
    ],
    
    // set your aplication time difference in seconds from GMT time
    'appTimeDiffFromGmt' => 0,
    
    /**
     *  Routing configurations
     *
     *  You can route any URL by regular expression. key MUST be regular expression and value may contain matched part
     *  NOTE: #1# equals to $1 or \\1
     *
     *  Example:
     *      $bootstrap['router'] = [
     *          '~^login~i' => 'Member/login',
     *          '#^products/(\d+)/.*#i' => 'product/view/#1#',
     *      ];
     *
     *  for 1st condition if the URL is `http://your-application.com/login` then
     *  Zamp considered as `http://your-application.com/Member/login`
     *
     *  for 2nd condition if the URL is `http://your-application.com/products/123/this_is_my_product_name` then
     *  Zamp considered as `http://your-application.com/product/view/123`
     *
     *  If the URL routed based on your conditions then you can check route information
     *  from \Zamp()->bootInfo('routeInfo')
     */
    'router' => [
        
    ],
    
    /**
     *  
     */
    'view' => [
        // name of your theme folder
        'themeName' => 'default',
        
        // view file extension like `php`, `tpl`, `twig`
        'fileExtension' => 'php',
        
        // yourCallback(): void
        'viewRenderCallback' => function() {
            $zamp = \Zamp();
            $data = $zamp->view->getAll();
            
            foreach($data as $k => $v)
                $$k = $v;
            
            $processing_time = \Zamp\StopWatch::getElapsed('Total_Runtime', 7);
            
            $actionFile = $zamp->view->actionFile();
            
            include $zamp->view->layoutFile()['fullPath'];
        },
    ],
    
    /**
     *  NOTE: Cache instance will be created only when it used first time, so if you
     *  need you can modify this configuration dynamically before using `$this->cache`
     */
    'cache' => [
        'driver' => 'NoCache', // NoCache, APCu, File, Redis
        'defaultTtl' => 3600, // in seconds
        'driverConfig' => [], // Refer driver config from `PATH_DETAILS['PLUGINS']/Zamp/Cache/{driver}.php`
    ],
    
    /**
     *  Set the module where you'll keep all modules config files.
     *  This is helpful to maintain module's distributed configuration file untouched.
     *  
     *  Zamp will check the requested module's configuration file under this given `modules/Config` folder, if
     *  it not found then it will try to load from corresponding modules/Config folder.
     *  
     *  Example: Lets say you've `Cron` module and its config file under `Cron/Config/Cron.noauto.php`. Now instead of
     *  directly editing `Cron/Config/Cron.noauto.php` file you can copy and paste into `Core/Config/Cron.noauto.php`.
     *  
     *  Now, Zamp will try to read from `Core/Config/Cron.noauto.php`, if it not found then it will be read from `Cron/Config/Cron.noauto.php`
     */
    'configFirstCheckUnder' => 'App',
    
    /**
     *  Set the module where you'll keep all modules view files.
     *  This is helpful to maintain module's distributed view file untouched.
     *  
     *  Zamp will check the requested action's view file under this given `modules/View` folder, if
     *  it not found then it will try to load from corresponding modules/View folder.
     *  
     *  Example: Lets say requested controller/action is `Cron/listJobs` and its view file under `Cron/View/{themeName}/Cron/listjobs.{fileExtension}`. Now instead of
     *  directly editing `Cron/View/{themeName}/Cron/listjobs.{fileExtension}` file you can copy and paste into `Core/View/{themeName}/Cron/listjobs.{fileExtension}`.
     *  
     *  Now, Zamp will try to read from `Core/View/{themeName}/Cron/listjobs.{fileExtension}`, if it not found then it will be read from `Cron/View/{themeName}/Cron/listjobs.{fileExtension}`
     */
    'viewFirstCheckUnder' => 'App',
    
    /**
     *  Default Email Transport to use for outgoing emails
     *  Refer \Zamp\Mailer::getTransport() method for options.
     */
    'defaultEmailTransport' => [
        '_object' => 'Swift_SmtpTransport',
        'host' => 'localhost',
        'port' => 25,
        'username' => '',
        'password' => '',
        'encryption' => 'tls',
    ],
    
    /**
     *  If you set $bootstrap['isDevelopmentPhase'] = false then the follwing settings will be used
     *  If you set sendEmailAlert = true in the following settings then you can use Zamp\Mailer::send() options
     */
    'errorHandler' => [
        'saveErrorIntoFolder' => PATH_DETAILS['TEMP'].'/errors',
        'errorFileFormat' => 'Y-m-d--h-i-s-a',
        'errorTimeDiffFromGmt' => 19800,
        'sendEmailAlert' => true,
        'intervalForSameErrorReAlert' => 300,
        'mailerSettings' => [
            'message' => [
                'from' => ['noreply@zampphp.org' => 'Zamp'],
                'to' => ['phpmathan@gmail.com' => 'Mathan Kumar'],
                'subject' => 'Application Error',
                'body' => 'Hi, Your application received some errors. Immediately look it out.',
                'contentType' => 'text/html',
            ],
        ],
    ],
    
    /**
     *  Set your application namespace
     *  
     *  If class name contain `Controller` then it will be resolve into Controller folder
     *  Example: modules\Core\IndexController -> resolves to PATH_DETAILS['APPLICATION']/Core/Controller/IndexController.php
     *  
     *  If class found just after the module then it will be resolve into Model folder
     *  Example: modules\Core\Misc -> resolves to PATH_DETAILS['APPLICATION']/Core/Model/Misc.php
     *  
     *  If class NOT found just after the module then it will resolve relative to the namespace path
     *  Example: modules\Core\Helpers\Array2Xml -> resolves to PATH_DETAILS['APPLICATION']/Core/Helpers/Array2Xml.php
     *  Example: modules\Core\Plugins\Http\Request\Hander -> resolves to PATH_DETAILS['APPLICATION']/Core/Plugins/Http/Request/Hander.php
     */
    'applicationNameSpace' => 'modules',
    
    /**
     *  Set your default controller name withOUT `Controller` suffix
     *  
     *  NOTE: If your controller not found in `Core` module, then update the same in `PATH_DETAILS['APPLICATION']/classMapping.php`
     */
    'defaultController' => 'Index',
    
    // Set your default action name
    'defaultAction' => 'index',
    
    // Encryption secret key string. Zamp prefer you to set some random characters as secret key
    'encryptionSecretKey' => 'Ch644QqA9P2eXZRDUPkt',
    
    /**
     *  Callback function to call when 404 error occured in NON development phase
     *  (ie., config `isDevelopmentPhase` set to false)
     *  
     *  yourCallback(String $errorType, String $errorCode)?: bool
     *  
     *  $errorType will be either `controllerNotFound` or `methodNotFound`
     *  
     *  If your callback return `true` then Zamp assume you've made internal/bootInfo changes, so it will continue further, otherwise Zamp will show 404 error page.
     *  
     *  Example: If `controllerNotFound` re-route to ErrorController
     *      myCallback($errorType, $errorCode) {
     *          if($errorType != 'controllerNotFound')
     *              return false;
     *          
     *          \Zamp()->bootInfoSet('controller', [
     *              'request' => 'Error',
     *              'class' => modules\Core\ErrorController::class,
     *              'path' => modules\Core\ErrorController::modulePath().'/Controller/ErrorController.php',
     *          ]);
     *          
     *          \Zamp()->bootInfoSet('action', 'showError');
     *          
     *          return true;
     *      }
     *  
     *  refer Zamp\doCall() function in Zamp/Core.php for callback functions
     */
    'routing404Callback' => '',
    
    /**
     *  Callbacks to call before calling requested Controller->action() (ie., before routing)
     *  NOTES: If your callback return `false` then following registered callbacks not called.
     *  
     *  refer Zamp\doCall() function in Zamp/Core.php for callback functions
     */
    'onBeforeProcessCallbacks' => [
        
    ],
    
    /**
     *  Callbacks to call after calling requested Controller->action() (ie., after routing)
     *  NOTE: If your callback return `false` then following registered callbacks not called.
     *  
     *  refer Zamp\doCall() function in Zamp/Core.php for callback functions
     */
    'onAfterProcessCallbacks' => [
        
    ],
    
    /**
     *  Callback to call whenever module configurations loaded
     *  
     *  yourCallback(String $moduleName, String $applicationName, Array $configFilesFullPath): void
     *  
     *  refer Zamp\doCall() function in Zamp/Core.php for callback functions
     */
    'onModuleConfigLoadedCallback' => '',
];
/* END OF FILE */
