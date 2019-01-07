<?php

namespace Zamp\Exceptions;

class PhpErrorException extends \Exception {
    public function __construct($errno, $errstr, $errfile, $errline) {
		static $errorTypes = [
			E_ERROR => 'Error',
			E_WARNING => 'Warning',
			E_PARSE => 'Parsing Error',
			E_NOTICE => 'Notice',
			E_CORE_ERROR => 'Core Error',
			E_CORE_WARNING => 'Core Warning',
			E_COMPILE_ERROR => 'Compile Error',
			E_COMPILE_WARNING => 'Compile Warning',
			E_USER_ERROR => 'User Error',
			E_USER_WARNING => 'User Warning',
			E_USER_NOTICE => 'User Notice',
			E_STRICT => 'Runtime Notice',
		];
		
		$errorType = $errorTypes[$errno] ?? 'Unknown Error';
		parent::__construct("[$errorType] $errstr (@line $errline in file $errfile).", $errno);
	}
}
/* END OF FILE */