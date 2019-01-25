<?php

namespace Zamp\Cache;

use \Zamp\General;

class File extends AbstractClass {
    protected $config = [
        'path' => null,
    ];
    
    public function __construct($config, $ttl) {
        $config = array_merge($this->config, $config);
        
        if(empty($config['path']))
            $config['path'] = PATH_DETAILS['TEMP'].'/cache';
        
        $config['path'] = realpath($config['path']);
        
        if(!is_dir($config['path'])) {
            if(!mkdir($config['path'], 0777, true))
                throw new \Zamp\Exceptions\FolderCreateFailed("File cache folder `{$config['path']}` creation failed.");
        }
        elseif(!is_writable($config['path']))
            throw new \Zamp\Exceptions\PathNotWritable("File cache folder `{$config['path']}` is not writable.");
        
        parent::__construct($config, $ttl);
    }
    
    public function driverName() {
        return 'File';
    }
    
    private function _filename($module) {
        return $this->config['path'].'/fileCache'.$module.'.php';
    }
    
    public function get($key) {
        $key = explode('/', $key);
        
        $module = $key[0];
        $module = $this->_filename($module);
        
        $value = @include $module;
        
        if($value === false)
            return null;
        
        if(time() > $value['expire']) {
            unlink($module);
            return null;
        }
        
        return $this->getCache($key, $value['data']);
    }
    
    public function set($key, $value, $ttl=null) {
        $ttl = $ttl ?? $this->ttl;
        $ttl = (int) $ttl;
        
        $key = explode('/', $key);
        
        $module = array_shift($key);
        $module = $this->_filename($module);
        
        if($key)
            $value = General::setMultiArrayValue($key, $value);
        
        $previousData = @include $module;
        $previousData = $previousData ?$previousData['data'] :[];
        
        if(isset($value) && (array) $value === $value && (array) $previousData === $previousData)
            $value = General::arrayMergeRecursiveDistinct($previousData, $value);
        
        $value = [
            'expire' => time() + $ttl,
            'data' => $value,
        ];
        
        $value = "<?php\nreturn ".var_export($value, true).";\n";
        
        file_put_contents($module, $value, LOCK_EX);
        
        return true;
    }
    
    public function delete($key) {
        $key = explode('/', $key);
        
        $module = array_shift($key);
        $module = $this->_filename($module);
        
        if(!$key) {
            @unlink($module);
            return true;
        }
        
        $value = @include $module;
        $value = $value ?$value['data'] :[];
        
        if((array) $value === $value) {
            General::unsetMultiArrayValue($key, $value);
            
            if(!$value) {
                @unlink($module);
                return true;
            }
            
            $value = [
                'expire' => time() + $this->ttl,
                'data' => $value,
            ];
            
            $value = "<?php\nreturn ".var_export($value, true).";\n";
            
            file_put_contents($module, $value, LOCK_EX);
            
            return true;
        }
        
        @unlink($module);
        return true;
    }
    
    public function clear() {
        if($dh = opendir($this->config['path'])) {
            while(($file = readdir($dh)) !== false) {
                if($file == '.' || $file == '..')
                    continue;
                
                $file = $this->config['path'].'/'.$file;
                
                if(preg_match('~^fileCache~', basename($file)))
                    unlink($file);
            }
            
            closedir($dh);
        }
        
        return true;
    }
}
/* END OF FILE */
