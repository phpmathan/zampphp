<?php

namespace Zamp;

class Security extends Base {
    // Random Security code
    public $secretKey;
    
    public function __construct() {
        $this->secretKey = Core::system()->config['bootstrap']['encryptionSecretKey'] ?? 'aK1fegBuu7Fy2kgboHuu';
    }
    
    // Encode the given string
    public function encode($str, $key='', $identifier='$', $cipher='bf-cbc') {
        $key = $key ?: $this->secret_key;
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length($cipher));
        $str = openssl_encrypt($str, $cipher, $key, 0, $iv);
        
        return General::base64Encode($str.$identifier.base64_encode($iv));
    }
    
    // Decode the given encoded string
    public function decode($cryptArr, $key='', $identifier='$', $cipher='bf-cbc') {
        $key = $key ?: $this->secret_key;
        $cryptArr = General::base64Decode($cryptArr);
        $cryptArr = explode($identifier, $cryptArr);
        
        if(!isset($cryptArr[1]))
            return;
        
        $data = openssl_decrypt($cryptArr[0], $cipher, $key, 0, base64_decode($cryptArr[1]));
        
        return $data !== false ?$data :null;
    }
    
    // Remove Invisible Characters. This prevents sandwiching null characters between ascii characters, like Java\0script
    public static function removeInvisibleCharacters($str, $urlEncoded=true) {
        $nonDisplayables = [];
        
        // every control character except newline (dec 10),
        // carriage return (dec 13) and horizontal tab (dec 09)
        if($urlEncoded) {
            $nonDisplayables[] = '/%0[0-8bcef]/';   // url encoded 00-08, 11, 12, 14, 15
            $nonDisplayables[] = '/%1[0-9a-f]/';    // url encoded 16-31
        }
        
        $nonDisplayables[] = '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/S';    // 00-08, 11, 12, 14-31, 127
        
        do {
            $str = preg_replace($nonDisplayables, '', $str, -1, $count);
        }
        while($count);
        
        return $str;
    }
    
    // Sanitize Filename
    public static function sanitizeFilename($str, $relativePath=false) {
        $bad = [
            '../', '<!--', '-->', '<', '>',
            "'", '"', '&', '$', '#',
            '{', '}', '[', ']', '=',
            ';', '?', '%20', '%22',
            '%3c',      // <
            '%253c',    // <
            '%3e',      // >
            '%0e',      // >
            '%28',      // (
            '%29',      // )
            '%2528',    // (
            '%26',      // &
            '%24',      // $
            '%3f',      // ?
            '%3b',      // ;
            '%3d'       // =
        ];
        
        if(!$relativePath) {
            $bad[] = './';
            $bad[] = '/';
        }
        
        $str = self::removeInvisibleCharacters($str, false);
        
        do {
            $old = $str;
            $str = str_replace($bad, '', $str);
        }
        while($old !== $str);
        
        return stripslashes($str);
    }
    
    // Strip Image Tags
    public function stripImageTags($str) {
        return preg_replace(
            [
                '#<img[\s/]+.*?src\s*=\s*(["\'])([^\\1]+?)\\1.*?\>#i',
                '#<img[\s/]+.*?src\s*=\s*?(([^\s"\'=<>`]+)).*?\>#i'
            ],
            '\\2',
            $str
        );
    }
}
/* END OF FILE */