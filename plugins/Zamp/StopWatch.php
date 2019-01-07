<?php

namespace Zamp;

class StopWatch {
    private static $_timer = [];
    
    public static function start($name) {
        self::reset($name);
        self::$_timer[$name] = [
            'start' => hrtime(true)
        ];
    }
    
    public static function stop($name, $isStreched=false) {
        if($isStreched || !isset(self::$_timer[$name]['stop']))
            self::$_timer[$name]['stop'] = hrtime(true);
        
        self::$_timer[$name]['taken'] = self::getStop($name) - self::getStart($name);
    }
    
    public static function stretch($name) {
        self::$_timer[$name]['stop'] = hrtime(true);
        self::$_timer[$name]['taken'] = self::getStop($name) - self::getStart($name);
    }
    
    public static function getStart($name) {
        if(!isset(self::$_timer[$name]['start']))
            self::start($name);
        
        return self::$_timer[$name]['start'];
    }
    
    public static function getStop($name) {
        if(!isset(self::$_timer[$name]['stop']))
            self::stop($name);
        
        return self::$_timer[$name]['stop'];
    }
    
    public static function getElapsed($name, $length=null) {
        if(!isset(self::$_timer[$name]['taken']))
            self::stop($name);
        
        if(!$length)
            return self::$_timer[$name]['taken'];
        
        return sprintf('%0.'.$length.'f', self::$_timer[$name]['taken'] / 1e+9);
    }
    
    public static function reset($name) {
        if(isset(self::$_timer[$name]))
            unset(self::$_timer[$name]);
    }
    
    public static function getAll($length=null) {
        if(!$length)
            return self::$_timer;
        
        $format = '%0.'.$length.'f';
        
        $out = [];
        
        foreach(self::$_timer as $name => $data) {
            $out[$name] = $data;
            
            if(!isset($data['taken']))
                continue;
            
            $out[$name]['taken'] = sprintf($format, $data['taken'] / 1e+9);
        }
        
        return $out;
    }
}
/* END OF FILE */