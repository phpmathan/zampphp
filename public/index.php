<?php

error_reporting(E_ALL);

$pathInfo = [
    'PUBLIC' => __DIR__,
    'PROJECT' => '',
    'APPLICATION' => '',
    'PLUGINS' => '',
    'TEMP' => '',
];

$pathInfo['PROJECT'] = realpath($pathInfo['PUBLIC'].'/../');
$pathInfo['APPLICATION'] = $pathInfo['PROJECT'].'/application';
$pathInfo['PLUGINS'] = $pathInfo['PROJECT'].'/plugins';
$pathInfo['TEMP'] = $pathInfo['PROJECT'].'/tmp';

define('PATH_DETAILS', $pathInfo);
unset($pathInfo);

/**
 *    default timezone for direct calling of date() like functions
 *    You're advised to avoid using date() or time() functions in your framework projects, instead use
 *    Zamp\Core->systemTime() method
 *
 *    NOTE: calling Zamp\Core->systemTime() based on GMT time
 */
date_default_timezone_set('GMT');

// Ensure the current directory is pointing to the public directory
chdir(PATH_DETAILS['PUBLIC']);

require_once PATH_DETAILS['PROJECT'].'/functions.php';
require_once PATH_DETAILS['PROJECT'].'/bootstrap.php';
require_once PATH_DETAILS['PLUGINS'].'/Zamp/Core.php';

//$system = Zamp\System::obj()->bootstrap($bootstrap);
$system = Zamp\Core::getInstance(Zamp\System::class)->bootstrap($bootstrap);
$system->run();
/* END OF FILE */
