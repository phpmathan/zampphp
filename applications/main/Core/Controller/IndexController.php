<?php

namespace app\Modules\Core;

class IndexController extends \Zamp\Controller {
    public function index() {
        $this->view->page_title = 'Welcome to Zamp PHP';
    }
}
/* END OF FILE */