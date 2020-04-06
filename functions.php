<?php

function Zamp() {
    return \Zamp\Core::system();
}

function debug($data, $exit=true, $dump=false) {
    if(Zamp\NEXT_LINE == '<br/>')
        echo "<PRE>";
    
    if($dump)
        @var_dump($data);
    else
        @print_r($data);
    
    if(Zamp\NEXT_LINE == '<br/>')
        echo "</PRE>";
    
    if($exit)
        Zamp\cleanExit();
}

function varDump($mixed=null) {
    ini_set("xdebug.overload_var_dump", "off");
    
    ob_start();
    var_dump($mixed);
    $content = ob_get_contents();
    ob_end_clean();
    
    return $content;
}

function debugData($data, $name='', $append=true) {
    $options = LOCK_EX;
    
    if($append)
        $options = $options | FILE_APPEND;
    
    $data = $name === true ?varDump($data) :print_r($data, true);
    
    $log = "// ".date('r')." {$name}\n";
    $log .= $data."/*************************************************************/\n";
    
    file_put_contents(PATH_DETAILS['PROJECT'].'/debug.txt', $log, $options);
}
/* END OF FILE */
