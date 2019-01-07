<?php

namespace Zamp;

class General {
    // Method used to find the value in multidimentional array
    public static function arraySearch($needle, $haystack, $strict=false, $path=[]) {
        if((array) $haystack !== $haystack)
            return false;
        
        foreach($haystack as $key => $val) {
            if((array) $val === $val && $subPath = self::arraySearch($needle, $val, $strict, $path)) {
                $path = array_merge($path, [$key], $subPath);
                return $path;
            }
            elseif((!$strict && $val == $needle) || ($strict && $val === $needle)) {
                $path[] = $key;
                return $path;
            }
        }
        
        return false;
    }
    
    // Method used to find the key in multidimentional array
    public static function arrayKeyExists($needle, $haystack) {
        if((array) $haystack !== $haystack)
            return false;
        
        foreach($haystack as $key => $value) {
            if($needle === $key)
                return [$key];
            
            if((array) $value === $value) {
                $temp = self::arrayKeyExists($needle, $value);
                
                if($temp !== false)
                    return array_merge([$key], $temp);
            }
        }
        
        return false;
    }
    
    // Method Used to get unique array using serialize.
    public static function arrayUnique($myArray) {
        if((array) $myArray !== $myArray)
            return $myArray;
        
        foreach($myArray as &$myvalue)
            $myvalue = serialize($myvalue);
        
        $myArray = array_unique($myArray);
        
        foreach($myArray as &$myvalue)
            $myvalue = unserialize($myvalue);
        
        return $myArray;
    }
    
    // Method used to convert the array to an object.
    public static function array2Object($data) {
        if((array) $data !== $data)
            return $data;
        
        $object = new \stdClass;
        
        if(!$data)
            return $object;
        
        foreach($data as $name => $value) {
            if($name == '')
                continue;
            
            $isNumericArray = false;
            
            if((array) $value === $value) {
                foreach($value as $k => $v) {
                    if(is_numeric($k)) {
                        $isNumericArray = true;
                        break;
                    }
                }
            }
            
            $object->$name = $isNumericArray ?$value :self::array2Object($value);
        }
        
        return $object;
    }
    
    // Method used to convert the object to an array.
    public static function object2Array($data) {
        if((object) $data !== $data && (array) $data !== $data)
            return $data;
        
        if((object) $data === $data)
            $data = get_object_vars($data);
        
        return array_map(['self', 'object2Array'], $data);
    }
    
    // Method used to generate/set multi dimentional array.
    public static function setMultiArrayValue($array, $value=[], $source=[]) {
        if(!$array || (array) $array !== $array)
            return $value;
        
        $temp =& $source;
        
        foreach($array as $item) {
            if(!isset($temp[$item]) || (array) $temp[$item] !== $temp[$item])
                $temp[$item] = [];
            
            $temp =& $temp[$item];
        }
        
        $temp = $value;
        
        return $source;
    }
    
    // Method used to get the value from the multi dimentional array
    public static function getMultiArrayValue($keys, $array, $defaultValue=null) {
        if(!$keys || (array) $keys !== $keys)
            return $array;
        
        foreach($keys as $key) {
            if(!isset($array[$key]))
                return $defaultValue;
            
            $array = $array[$key];
        }
        
        return $array;
    }
    
    // Method used to unset the value from the multi dimentional array
    public static function unsetMultiArrayValue($keys, &$reference) {
        if(!$keys || (array) $keys !== $keys)
            return false;
        
        $size = count($keys);
        
        $i = 1;
        
        foreach($keys as $key) {
            if(!isset($reference[$key]))
                return false;
            elseif($size == $i) {
                unset($reference[$key]);
                return true;
            }
            
            $reference =& $reference[$key];
            
            $i++;
        }
    }
    
    /**
     *  Merges 2 arrays recursively, replacing entries with string keys with values from 2nd array.
     *  If the entry or the next value to be assigned is an array, then it automagically treats both arguments as an array.
     *  Numeric entries are appended, not replaced, but only if they are unique.
     */
    public static function arrayMergeRecursiveDistinct($first, $second) {
        $second = [$second];
        
        if((array) $first !== $first)
            $first = $first ?[$first] :[];
        
        foreach($second as $append) {
            if((array) $append !== $append)
                $append = [$append];
            
            foreach($append as $key => $value) {
                if(!isset($first[$key]) && !array_key_exists($key, $first) && !((int) $key === $key || is_numeric($key))) {
                    $first[$key] = $value;
                    continue;
                }
                
                if((array) $value === $value || (isset($first[$key]) && (array) $first[$key] === $first[$key]))
                    $first[$key] = self::arrayMergeRecursiveDistinct((isset($first[$key]) ?$first[$key] :[]), $value);
                elseif((int) $key === $key || is_numeric($key)) {
                    if(!in_array($value, $first))
                        $first[] = $value;
                }
                else
                    $first[$key] = $value;
            }
        }
        
        return $first;
    }
    
    // Check, if the connection is via SSL
    public static function isSslConnection() {
        return (
            (isset($_SERVER['SERVER_PORT']) && ($_SERVER['SERVER_PORT'] == '443'))
                ||
            (isset($_SERVER['HTTP_X_FORWARDED_PORT']) && ($_SERVER['HTTP_X_FORWARDED_PORT'] == '443'))
                ||
            (isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] == '1' || strtolower($_SERVER['HTTPS']) == 'on'))
                ||
            (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) == 'https')
        );
    }
    
    /**
     *  Shorten an multidimensional array into a single dimensional array concatenating all keys with separator.
     *  
     *  ['country' => [0 => ['name' => 'Bangladesh', 'capital' => 'Dhaka']]]
     *      to ['country.0.name' => 'Bangladesh', 'country.0.capital' => 'Dhaka']
     */
    public static function arrayShort($inputArray, $path=null, $separator='.') {
        $data = [];
        
        if($path !== null)
            $path .= $separator;
        
        foreach($inputArray as $key => &$value) {
            if((array) $value !== $value)
                $data[$path.$key] = $value;
            else
                $data = array_merge($data, self::arrayShort($value, $path.$key, $separator));
        }
        
        return $data;
    }
    
    /**
     *  Unshorten a single dimensional array into multidimensional array.
     *  
     *  ['country.0.name' => 'Bangladesh', 'country.0.capital' => 'Dhaka']
     *      to ['country' => [0 => ['name' => 'Bangladesh', 'capital' => 'Dhaka']]]
     */
    public static function arrayUnShort($data, $separator='.') {
        $result = [];
        
        foreach($data as $key => $value) {
            if(strpos($key, $separator) !== false) {
                $str = explode($separator, $key, 2);
                $result[$str[0]][$str[1]] = $value;
                
                if(strpos($str[1], $separator))
                    $result[$str[0]] = self::arrayUnShort($result[$str[0]], $separator);
            }
            else
                $result[$key] = is_array($value) ?self::arrayUnShort($value, $separator) :$value;
        }
        
        return $result;
    }
    
    // Clear file cache
    public static function invalidate($file, $force=true) {
        return opcache_invalidate($file, $force);
    }
    
    // URL friendly base64 data
    public static function base64Clean($data, $type='encode') {
        if($type == 'encode')
            return rtrim(strtr($data, '+/', '-_'), '=');
        else
            return str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '=', STR_PAD_RIGHT);
    }
    
    // URL friendly base64 encode
    public static function base64Encode($data) {
        return self::base64Clean(base64_encode($data));
    }
    
    // URL friendly base64 decode
    public static function base64Decode($data, $strict=false) {
        return base64_decode(self::base64Clean($data, 'decode'), $strict);
    }
    
    // Set server response header Status
    public static function setHeader($code=200, $text='') {
        if(headers_sent())
            return false;
        
        $statusCode = [
            100 => 'Continue',
            101 => 'Switching Protocols',
            102 => 'Processing',
            200 => 'OK',
            201 => 'Created',
            202 => 'Accepted',
            203 => 'Non-Authoritative Information',
            204 => 'No Content',
            205 => 'Reset Content',
            206 => 'Partial Content',
            207 => 'Multi-Status',
            208 => 'Already Reported',
            300 => 'Multiple Choices',
            301 => 'Moved Permanently',
            302 => 'Found',
            303 => 'See Other',
            304 => 'Not Modified',
            305 => 'Use Proxy',
            307 => 'Temporary Redirect',
            400 => 'Bad Request',
            401 => 'Unauthorized',
            402 => 'Payment Required',
            403 => 'Forbidden',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            406 => 'Not Acceptable',
            407 => 'Proxy Authentication Required',
            408 => 'Request Timeout',
            409 => 'Conflict',
            410 => 'Gone',
            411 => 'Length Required',
            412 => 'Precondition Failed',
            413 => 'Request Entity Too Large',
            414 => 'Request-URI Too Long',
            415 => 'Unsupported Media Type',
            416 => 'Requested Range Not Satisfiable',
            417 => 'Expectation Failed',
            426 => 'Upgrade Required',
            428 => 'Precondition Required',
            429 => 'Too Many Requests',
            431 => 'Request Header Fields Too Large',
            451 => 'Unavailable For Legal Reasons',
            500 => 'Internal Server Error',
            501 => 'Not Implemented',
            502 => 'Bad Gateway',
            503 => 'Service Unavailable',
            504 => 'Gateway Timeout',
            505 => 'HTTP Version Not Supported',
            510 => 'Not Extended',
            511 => 'Network Authentication Required',
            598 => 'Network read timeout error',
            599 => 'Network connect timeout error'
        ];
        
        if($code == '' || !is_numeric($code))
            throw new \Exception('Status codes must be numeric');
        
        if($text == '' && isset($statusCode[$code]))
            $text = $statusCode[$code];
        
        if($text == '')
            throw new \Exception('No status text available. Please check your status code number or supply your own message text.');
        
        $server_protocol = $_SERVER['SERVER_PROTOCOL'] ?? false;
        
        if(substr(php_sapi_name(), 0, 3) == 'cgi')
            header("Status: $code $text", true);
        elseif($server_protocol == 'HTTP/1.1' || $server_protocol == 'HTTP/1.0')
            header($server_protocol." $code $text", true, $code);
        else
            header("HTTP/1.1 $code $text", true, $code);
        
        return true;
    }
}
/* END OF FILE */