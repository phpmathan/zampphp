<?php

namespace Zamp;

class Session implements \SessionHandlerInterface, \SessionIdInterface {
    private static $_info = [
        'isStarted' => false,
        'profile' => 'default',
        'profiles' => [
            'default' => [
                'status' => 'notOpened', // notOpened, opened, closed, reOpened
                'config' => [],
            ],
        ],
    ];
    
    private static function _prepare($config) {
        $default = session_get_cookie_params();
        
        $config = array_merge([
            'sessionId' => null,
            'sessionName' => 'ZampPHP',
            'handler' => 'file',
            'savePath' => PATH_DETAILS['TEMP'].'/sessions',
            'cookieLifetime' => $default['lifetime'],
            'cookiePath' => $default['path'],
            'cookieDomain' => $default['domain'],
            'cookieSecure' => $default['secure'],
            'cookieHttpOnly' => $default['httponly'] ?? false,
            'cacheLimiter' => null,
            'cacheExpire' => null,
        ], $config);
        
        if($config['handler'] == 'file') {
            $savePath = realpath($config['savePath']);
            
            if(!is_dir($savePath)) {
                if(!mkdir($savePath, 0777, true))
                    throw new Exceptions\FolderCreateFailed("Session `savePath` folder <font color='blue'>{$savePath}</font> creation failed.");
            }
            elseif(!is_writable($savePath))
                throw new Exceptions\PathNotWritable("Session `savePath` folder <font color='blue'>{$savePath}</font> is not writable.");
            
            session_save_path($savePath);
        }
        
        ini_set('session.use_trans_sid', false);
        
        ini_set('session.sid_length', 40);
        ini_set('session.sid_bits_per_character', 4);
        
        session_name($config['sessionName']);
        
        if(empty($config['sessionId'])) {
            ini_set('session.use_strict_mode', true);
            ini_set('session.use_only_cookies', true);
            ini_set('session.use_cookies', true);
            
            $config['isInternalOnly'] = false;
        }
        else {
            ini_set('session.use_strict_mode', false);
            ini_set('session.use_only_cookies', false);
            ini_set('session.use_cookies', false);
            ini_set('session.use_trans_sid', false);
            ini_set('session.cache_limiter', null);
            
            session_id($config['sessionId']);
            
            $config['isInternalOnly'] = true;
        }
        
        session_set_cookie_params(
            $config['cookieLifetime'],
            $config['cookiePath'],
            $config['cookieDomain'],
            $config['cookieSecure'],
            $config['cookieHttpOnly']
        );
        
        if(isset($config['cacheLimiter']))
            session_cache_limiter($config['cacheLimiter']);
        
        if(isset($config['cacheExpire']))
            session_cache_expire($config['cacheExpire']);
        
        if($config['handler'] != 'file' && $config['handler'] != 'php')
            $config = doCall($config['handler'], [$config]);
        
        session_start();
        
        if(empty($config['sessionId']))
            $config['sessionId'] = session_id();
        
        return $config;
    }
    
    final public static function start($config) {
        if(self::isStarted())
            throw new Exceptions\Session('Session is already started!');
        
        register_shutdown_function('\session_write_close');
        
        Core::cleanExitCallbackSet('session_write_close', function() {
            \session_write_close();
        });
        
        self::$_info['isStarted'] = true;
        
        self::$_info['profiles']['default'] = [
            'status' => 'opened',
            'config' => self::_prepare($config),
        ];
    }
    
    final public static function info() {
        return self::$_info;
    }
    
    final public static function isStarted() {
        return self::$_info['isStarted'];
    }
    
    final public static function currentProfile() {
        return self::$_info['profile'];
    }
    
    final public static function profileSwitch($profile, $config=[]) {
        if(!self::isStarted())
            throw new Exceptions\Session('Session is not yet started!');
        
        if(self::$_info['profile'] == $profile)
            return false;
        
        self::writeClose(self::$_info['profile']);
        
        self::$_info['profile'] = $profile;
        
        if(isset(self::$_info['profiles'][$profile]))
            return self::reOpen($profile);
        
        if(empty($config['sessionId']))
            $config['sessionId'] = self::generateSessionId();
        
        self::$_info['profiles'][$profile] = [
            'status' => 'opened',
            'config' => self::_prepare($config),
        ];
        
        return true;
    }
    
    final public static function profileInfo($profile=null) {
        $profile = $profile ?? self::currentProfile();
        return self::$_info['profiles'][$profile];
    }
    
    final public static function getStatus($profile=null) {
        return self::profileInfo($profile)['status'] ?? 'notOpened';
    }
    
    final public static function isClosed($profile=null) {
        return self::getStatus($profile) == 'closed';
    }
    
    final public static function reOpen($profile=null) {
        $profile = $profile ?? self::currentProfile();
        
        if(!self::isClosed($profile))
            return false;
        
        ini_set('session.use_strict_mode', false);
        ini_set('session.use_only_cookies', false);
        ini_set('session.use_cookies', false);
        ini_set('session.use_trans_sid', false);
        ini_set('session.cache_limiter', null);
        
        $config = self::profileInfo($profile)['config'];
        
        session_name($config['sessionName']);
        session_id($config['sessionId']);
        
        session_start();
        
        self::$_info['profiles'][$profile]['status'] = 'reOpened';
        
        return true;
    }
    
    final public static function writeClose($profile=null) {
        if(!self::isStarted())
            return false;
        
        $profile = $profile ?? self::currentProfile();
        
        $status = self::getStatus($profile);
        
        if($status != 'opened' && $status != 'reOpened')
            return false;
        
        session_write_close();
        
        self::$_info['profiles'][$profile]['status'] = 'closed';
        
        return true;
    }
    
    public static function generateSessionId() {
        return bin2hex(openssl_random_pseudo_bytes(20));
    }
    
    public static function isValidSessionId($id) {
        if(empty($id) || !($id = trim($id)))
            return false;
        
        return preg_match('/^[a-f0-9]{40}$/', $id);
    }
    
    public function open($savePath, $sessionName) {
        return true;
    }
    
    public function close() {
        return true;
    }
    
    public function read($sessionId) {
        return '';
    }
    
    public function write($sessionId, $data) {
        return true;
    }
    
    public function destroy($sessionId) {
        return true;
    }
    
    public function gc($maxLifeTime) {
        return true;
    }
    
    public function create_sid() {
        return self::generateSessionId();
    }
}
/* END OF FILE */