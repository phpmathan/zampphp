<?php

namespace Zamp;

class View extends Base {
    protected $data = [];
    
    protected $_internal = [];
    
    public function setThemeName($name) {
        $this->_internal['themeName'] = $name;
    }
    
    public function getThemeName() {
        return $this->_internal['themeName'];
    }
    
    public function setFileExtension($extension) {
        $this->_internal['fileExtension'] = $extension;
    }
    
    public function getFileExtension() {
        return $this->_internal['fileExtension'];
    }
    
    public function actionFile($controller=null, $action=null) {
        if($controller === null)
            return $this->_internal['actionFile'] ?? [];
        
        if($controller === false) {
            $this->_internal['actionFile'] = [];
            return;
        }
        
        $this->_internal['actionFile'] = [
            'basePath' => $this->getThemeName().'/'.$controller.'/'.$action.'.'.$this->getFileExtension(),
        ];
    }
    
    public function actionFileCheck() {
        if(empty($this->_internal['actionFile']))
            return false;
        
        if(!empty($this->_internal['actionFile']['baseFolder']))
            return true;
        
        $basePath = $this->_internal['actionFile']['basePath'];
        
        $appFolder = Core::system()->bootInfo('application')['path'];
        
        $module = Core::system()->config['bootstrap']['viewFirstCheckUnder'];
        $baseFolder = $appFolder.'/'.$module;
        $fullPath = $baseFolder.'/View/'.$basePath;
        
        if(isFileExists($fullPath, 'view:'.$basePath)) {
            $this->_internal['actionFile']['baseFolder'] = $baseFolder;
            $this->_internal['actionFile']['fullPath'] = $fullPath;
            $this->_internal['actionFile']['underModule'] = $module;
            
            return true;
        }
        
        list($modulePath,) = explode('/Controller/', Core::system()->bootInfo('controller')['path'], 2);
        
        if($modulePath == $baseFolder)
            throw new \Exception("Controller action's view file not found in following path.<br/>- {$fullPath}");
        
        $baseFolder = $modulePath;
        $module = substr(strrchr($baseFolder, '/'), 1);
        $fullPath2 = $baseFolder.'/View/'.$basePath;
        
        if(isFileExists($fullPath2, 'view:'.$basePath)) {
            $this->_internal['actionFile']['baseFolder'] = $baseFolder;
            $this->_internal['actionFile']['fullPath'] = $fullPath2;
            $this->_internal['actionFile']['underModule'] = $module;
            
            return true;
        }
        
        throw new \Exception("Controller action's view file not found in any of the following path.<br/>- {$fullPath}<br/>- {$fullPath2}");
    }
    
    public function isRendered() {
        return $this->_internal['isRendered'] ?? false;
    }
    
    public function stopRender() {
        $this->_internal['isRendered'] = 'stopped';
    }
    
    public function render() {
        if($this->isRendered())
            return;
        
        $this->_internal['isRendered'] = true;
        
        if($callback = getConf('bootstrap/view/viewRenderCallback'))
            doCall($callback);
    }
    
    public function __get($key) {
		return $this->data[$key];
    }
    
    public function __set($key, $value=null) {
		$this->set($key, $value);
    }
    
    public function __isset($key) {
		return isset($this->data[$key]);
    }
    
    public function __unset($key) {
		$this->delete($key);
    }
    
    public function set($key, $value=null) {
        if((array) $key === $key)
            $this->data = $value === true ?General::arrayMergeRecursiveDistinct($this->data, $key) :array_merge($this->data, $key);
        else
            $this->data[$key] = $value;
    }
    
    public function get($key) {
        return $this->data[$key] ?? null;
    }
    
    public function delete($key) {
        if(isset($this->data[$key]))
            unset($this->data[$key]);
    }
    
    public function getAll() {
        return $this->data;
    }
    
    public function deleteAll() {
        $this->data = [];
    }
}
/* END OF FILE */