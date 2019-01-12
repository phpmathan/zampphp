<?php

namespace Zamp;

class Controller extends Application {
    //protected $_blockedMethods = [];
    
    //protected $_routingMethodErrorHandler = 'your-method-which-is-defined-in-the-same-class';
    
    final public function getBlockedMethods() {
        return isset($this->_blockedMethods) ?$this->_blockedMethods :[];
    }
    
    final public function getMethodErrorHandler() {
        return isset($this->_routingMethodErrorHandler) ?$this->_routingMethodErrorHandler :null;
    }
}
/* END OF FILE */
