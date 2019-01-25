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