<?php

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
     *  you can add more applications under your applications directory.
     *  array key is the URL's Controller name. "*" means all URL
     *  array value is application folder name
     *  Example: you've 2 applications called blog and news, so you can define the Application as
     *  $bootstrap['applications']['Blog'] = 'blog'; -> means if the URL start with 'blog' then the blog application (PATH_DETAILS['APPLICATIONS'].'blog') will be loaded. NOTE: Here 'Blog' Controller converted into an application and following name treated as new Controller.
     *  $bootstrap['applications']['*'] = 'news'; -> means for all URL (which are not matched in the defined applications) the news application (PATH_DETAILS['APPLICATIONS'].'news') will be loaded.
     *  Explanation:
     *  http://localhost/blog/add/post -> AddController->post() inside the blog application called.
     *  http://localhost/add -> AddController->yourDefaultAction() inside the news application called.
     */
    'applications' => [
        '*' => 'main',
    ],
    
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
     *  from `$this->_params` array which is Zamp\System property
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
            $zamp = \Zamp\Core::system();
            $data = $zamp->view->getAll();
            
            foreach($data as $k => $v)
                $$k = $v;
            
            $processing_time = \Zamp\StopWatch::getElapsed('Total_Runtime', 7);
            
            $actionFile = $zamp->view->actionFile();
            
            include PATH_DETAILS['APPLICATIONS'].'/main/Core/View/default/index.php';
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
    'configFirstCheckUnder' => 'Core',
    
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
    'viewFirstCheckUnder' => 'Core',
    
    /**
     *  Default Email Transport to use for outgoing emails
     *  Refer \Zamp\Mailers::getTransport() method for options.
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
        'saveErrorIntoFolder' => PATH_DETAILS['PROJECT'].'/app_errors',
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
     *  Set your application name
     *  
     *  If class name contain `Controller` then it will be resolve into Controller folder
     *  Example: app\Modules\Core\IndexController -> resolves to PATH_DETAILS['APPLICATIONS']/{APP-NAME}/Core/Controller/IndexController.php
     *  
     *  If class found just after the module then it will be resolve into Model folder
     *  Example: app\Modules\Core\Misc -> resolves to PATH_DETAILS['APPLICATIONS']/{APP-NAME}/Core/Model/Misc.php
     *  
     *  If class NOT found just after the module then it will resolve relative to the namespace path
     *  Example: app\Modules\Core\Helpers\Array2Xml -> resolves to PATH_DETAILS['APPLICATIONS']/{APP-NAME}/Core/Helpers/Array2Xml.php
     *  Example: app\Modules\Core\Plugins\Http\Request\Hander -> resolves to PATH_DETAILS['APPLICATIONS']/{APP-NAME}/Core/Plugins/Http/Request/Hander.php
     */
    'applicationNameSpace' => app\Modules::class,
    
    /**
     *  Set your default controller name withOUT `Controller` suffix
     *  
     *  NOTE: If your controller not found in `Core` module, then update the same in `applications/<your-application>/classMapping.php`
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
     *          \Zamp\Core::system()->bootInfoSet('controller', [
     *              'request' => 'Error',
     *              'class' => app\Modules\Core\ErrorController::class,
     *              'path' => app\Modules\Core\ErrorController::getModulePath().'/Controller/ErrorController.php',
     *          ]);
     *          
     *          \Zamp\Core::system()->bootInfoSet('action', 'showError');
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
     *  Callback to call when ever module configurations loaded
     *  
     *  yourCallback(String $moduleName, String $applicationName, Array $configFilesFullPath): void
     *  
     *  refer Zamp\doCall() function in Zamp/Core.php for callback functions
     */
    'onModuleConfigLoadedCallback' => '',
];

function debug($data, $exit=true, $dump=false) {
	echo "<PRE>";
	
	if($dump)
		@var_dump($data);
	else
		@print_r($data);
	
	echo "</PRE>";
	
	if($exit)
		Zamp\cleanExit();
}

function debugData($data, $name='', $append=true) {
	$options = LOCK_EX;
	
	if($append)
		$options = $options | FILE_APPEND;
	
	$log = '';
	
	if($name)
		$log .= "// {$name}\n";
	
	$log .= print_r($data, true)."\n/*************************************************************/\n";
	
	file_put_contents(PATH_DETAILS['PROJECT'].'/debug.txt', $log, $options);
}
/* END OF FILE */
