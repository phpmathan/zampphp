<?php

namespace Zamp;

class ErrorHandler extends Base {
    // Handling the Errors
    public function handleError($exception) {
        static $handling = false;
        
        restore_error_handler();
        restore_exception_handler();
        
        if($handling)
            $this->handleRecursiveError($exception);
        else {
            $handling = true;
            $this->displayException($exception);
        }
    }
    
    // PHP error handler overwritten by this errorHandler method
    public static function errorHandler($errno, $errstr, $errfile, $errline) {
        if(error_reporting() != 0)
            throw new Exceptions\PhpError($errno, $errstr, $errfile, $errline);
    }
    
    // Handling the Exceptions
    public static function exceptionHandler($exception) {
        Core::getInstance(self::class)->handleError($exception);
        cleanExit();
    }
    
    // Handling recursive errors
    protected function handleRecursiveError($exception) {
        echo '
        <html>
            <head>
                <title>Recursive Error</title>
            </head>
            <body>
                <h1>Recursive Error</h1>
                <pre>'.$exception->__toString().'</pre>
            </body>
        </html>';
    }
    
    // Returns error html or log file path
    public static function getErrorInfoHTML($exception, $errorHandlerConfig=null) {
        if(!isset($errorHandlerConfig))
            $errorHandlerConfig = Core::system()->config['bootstrap']['errorHandler'] ?? [];
        
        if(!isset($errorHandlerConfig['isDevelopmentPhase']))
            $errorHandlerConfig['isDevelopmentPhase'] = !empty(Core::system()->config['bootstrap']['isDevelopmentPhase']);
        
        $errorTimeDiffFromGmt = 0;
        
        if(isset($errorHandlerConfig['errorTimeDiffFromGmt']) && is_numeric($errorHandlerConfig['errorTimeDiffFromGmt']))
            $errorTimeDiffFromGmt = $errorHandlerConfig['errorTimeDiffFromGmt'];
        
        if($errorTimeDiffFromGmt < 1) {
            $timeZoneFormat = '-';
            $tempVal = -1 * $errorTimeDiffFromGmt;
        }
        else {
            $timeZoneFormat = '+';
            $tempVal = $errorTimeDiffFromGmt;
        }
        
        $hrs = floor($tempVal / 3600);
        $mins = floor(($tempVal - ($hrs * 3600)) / 60);
        $sec = $tempVal - ($hrs * 3600) - ($mins * 60);
        
        $timeZoneFormat .= $hrs.':'.$mins.($sec ?':'.$sec :'');
        
        $errorInfo = self::getErrorInfo($exception);
        
        ob_start();
        require_once PATH_DETAILS['PLUGINS'].'/Zamp/Exceptions/templates/exception.html.php';
        $errorOutput = ob_get_clean();
        
        if(!empty($errorHandlerConfig['isDevelopmentPhase'])) {
            return [
                'type' => 'html',
                'result' => $errorOutput
            ];
        }
        
        $saveErrorIntoFolder = (isset($errorHandlerConfig['saveErrorIntoFolder'])) ?rtrim($errorHandlerConfig['saveErrorIntoFolder'], '/') :PATH_DETAILS['TEMP'].'/errors';
        
        if(!is_dir($saveErrorIntoFolder))
            mkdir($saveErrorIntoFolder, 0777, true);
        
        $errorFileName = $errorHandlerConfig['errorFileFormat'] ?? 'YmdHis';
        $errorFileName = Core::system()->systemTime($errorFileName, $errorTimeDiffFromGmt).'.html';
        $errorFullFileName = realpath($saveErrorIntoFolder).'/'.$errorFileName;
        
        file_put_contents($errorFullFileName, $errorOutput, LOCK_EX);
        
        if(!empty($errorHandlerConfig['sendEmailAlert'])) {
            $sendErrorEmail = true;
            unset($errorInfo['traces']);
            $currentErrorHash = md5(implode('@@', $errorInfo));
            
            $errorHashFile = $saveErrorIntoFolder.'/mailed_error_hashes.php';
            $currentTime = Core::system()->systemTime(null, 0);
            $notifiedErrors = [];
            
            if(file_exists($errorHashFile))
                $notifiedErrors = include $errorHashFile;
            
            if(!isset($notifiedErrors[$currentErrorHash]))
                $notifiedErrors[$currentErrorHash] = [];
            
            if($notifiedErrors[$currentErrorHash]) {
                $lastError = array_pop($notifiedErrors[$currentErrorHash]);
                $notifiedErrors[$currentErrorHash][] = $lastError;
                
                $minDelay = $currentTime - $lastError['occurredOn'];
                
                if($minDelay < $errorHandlerConfig['intervalForSameErrorReAlert'])
                    $sendErrorEmail = false;
            }
            
            $notifiedErrors[$currentErrorHash][] = [
                'errorNo' => count($notifiedErrors[$currentErrorHash]) + 1,
                'occurredOn' => $currentTime,
                'errorFile' => $errorFileName,
                'notified' => $sendErrorEmail ?'Yes': 'No',
            ];
            
            file_put_contents($errorHashFile, "<?php\nreturn ".var_export($notifiedErrors, true).";\n", LOCK_EX);
            
            if($sendErrorEmail) {
                $errorHandlerConfig['mailerSettings']['message']['body'] .= "<br/><br/>Error Hash: {$currentErrorHash}<br/><br/>Events: <pre style='font-size:12px;'>".json_encode($notifiedErrors[$currentErrorHash], JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)."</pre>";
                
                if(!isset($errorHandlerConfig['mailerSettings']['attachments']))
                    $errorHandlerConfig['mailerSettings']['attachments'] = [];
                
                $errorHandlerConfig['mailerSettings']['attachments'][] = [
                    'id' => 'errorInfoFile',
                    'filePath' => $errorFullFileName,
                    'fileName' => $errorFileName,
                ];
                
                Mailer::send($errorHandlerConfig['mailerSettings']);
            }
        }
        
        return [
            'type' => 'log',
            'result' => $errorFullFileName
        ];
    }
    
    // Displaying the exception or error screen or Sending error alert email if configured
    protected function displayException($exception) {
        $result = self::getErrorInfoHTML($exception);
        
        if($result['type'] == 'log')
            Core::system()->showErrorPage(500);
        else
            echo $result['result'];
        
        cleanExit();
    }
    
    // Collecting the error information
    public static function getErrorInfo($exception) {
        if($exception instanceof Exceptions\PhpError) {
            $traces = $exception->getTrace();
            $errorInfo = [];
            
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
                E_STRICT => 'Runtime Notice'
            ];
            
            $errorInfo['code'] = $traces[0]['args'][0];
            
            if(isset($errorTypes[$errorInfo['code']]))
                $errorInfo['text'] = ($errorText = $errorTypes[$errorInfo['code']]) ?$errorText :'Unknown Error';
            else
                $errorInfo['text'] = 'Unknown Error';
            
            $errorInfo['name'] = $traces[0]['args'][1];
        }
        else {
            $errorInfo['code'] = $exception->getCode();
            $errorInfo['text'] = 'Exception';
            $errorInfo['name'] = get_class($exception);
        }
        
        $errorInfo['message'] = $exception->getMessage();
        $errorInfo['traces'] = self::getTraces($exception);
        
        return $errorInfo;
    }
    
    // Getting the source code line to display in the error screen
    public static function getSourceCode($fileName, $errorInLine) {
        if(!is_readable($fileName))
            return '';
        
        $content = explode('<br />', highlight_file($fileName, true));
        
        $beginLine = max($errorInLine - 3, 1);
        $endLine = min($errorInLine + 3, count($content));
        
        $lines = [];
        
        for($i=$beginLine; $i<=$endLine; $i++) {
            $lines[] = '<li'.($i == $errorInLine ?' class="selected"' :'').'>'.$content[$i - 1].'</li>';
        }
        
        return '<ol start="'.max($errorInLine - 3, 1).'">'.implode("\n", $lines).'</ol>';
    }
    
    // Getting the exception trace path
    protected static function getTraces($exception, $format='html') {
        $traceData = $exception->getTrace();
        
        if($exception instanceof Exceptions\PhpError)
            array_shift($traceData);
        
        array_unshift($traceData, [
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'function' => '',
            'class' => null,
            'type' => null,
            'args' => [],
        ]);
        
        $traces = [];
        
        if($format == 'html') {
            $lineFormat = 'at <strong>%s%s%s</strong>(%s)<br />in <em>%s</em> line %s <a href="#" onclick="toggle(\'%s\'); return false;">...</a><br /><ul class="code" id="%s" style="display: %s">%s</ul>';
        }
        else
            $lineFormat = 'at %s%s%s(%s) in %s line %s';
        
        $count = count($traceData);
        
        for($i=0; $i<$count; $i++) {
            $line = $traceData[$i]['line'] ?? null;
            $file = $traceData[$i]['file'] ?? null;
            $args = $traceData[$i]['args'] ?? [];
            
            $data = sprintf($lineFormat, ($traceData[$i]['class'] ?? ''), ($traceData[$i]['type'] ?? ''), $traceData[$i]['function'], self::formatArgs($args, false, $format), $file, null === $line ?'n/a' :$line, 'trace_'.$i, 'trace_'.$i, $i == 0 ?'none' :'none', self::getSourceCode($file, $line));
            
            if($traceData[$i]['function'] == '')
                $data = preg_replace('~^at (.*?)in ~', 'in ', $data);
            
            $traces[] = $data;
        }
        
        return $traces;
    }
    
    // Formatting trace arguments
    public static function formatArgs($args, $single=false, $format='html') {
        $result = [];
        
        $single and $args = [$args];
        
        foreach($args as $key => $value) {
            if((object) $value === $value)
                $formattedValue = ($format == 'html' ?'<em>object</em>' :'object').sprintf("('%s')", get_class($value));
            elseif((array) $value === $value)
                $formattedValue = ($format == 'html' ?'<em>array</em>' :'array').sprintf("(%s)", self::formatArgs($value));
            elseif((string) $value === $value)
                $formattedValue = ($format == 'html' ?sprintf("'%s'", $value) :"'$value'");
            elseif(null === $value)
                $formattedValue = ($format == 'html' ?'<em>null</em>' :'null');
            else
                $formattedValue = $value;
            
            $result[] = is_numeric($key) ?$formattedValue :sprintf("'%s' => %s", $key, $formattedValue);
        }
        
        return implode(', ', $result);
    }
}
/* END OF FILE */