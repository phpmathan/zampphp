<?php

namespace Zamp;

class Base {
    public static function obj(...$args) {
        return Core::getInstance(static::class, $args);
    }
}
/* END OF FILE */