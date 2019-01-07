<?php

namespace Zamp;

class Mailer {
    private static function _init() {
        static $initiated = false;
        
        if(!$initiated) {
            $initiated = true;
            
            Core::setVendorPaths(PATH_DETAILS['PLUGINS'].'/Doctrine/Common/Lexer', \Doctrine\Common\Lexer::class);
            Core::setVendorPaths(PATH_DETAILS['PLUGINS'].'/EmailValidator', \Egulias\EmailValidator::class);
            Core::skipAutoLoaderFor('Swift_');
            
            require_once PATH_DETAILS['PLUGINS'].'/swiftmailer/swift_required.php';
        }
    }
    
    /**
     *  $settings = [
     *       '_object' => 'class name or closer which will return Swift_Transport object',
     *       
     *       // set{key-name}({value});
     *       'host' => '127.0.0.1', // smtp host
     *       'port' => 25, // smtp port
     *       'timeout' => 60, // smtp timeout
     *       'encryption' => 'tls', // smtp protocol
     *       'username' => '', // smtp username
     *       'password' => '', // smtp password
     *       
     *       '_handler' => 'yourCallback(Object Swift_Transport): Swift_Transport',
     *   ]
     */
    public static function getTransport($settings, $id='default') {
        static $transports = [];
        
        if(isset($transports[$id]))
            return $transports[$id];
        
        self::_init();
        
        if(!isset($settings['_object']))
            throw new Exceptions\Mailer("Transport settings `_object` is required.");
        
        if(is_callable($settings['_object']))
            $obj = $settings['_object']();
        elseif(gettype($settings['_object']) === 'string')
            $obj = new $settings['_object']();
        else
            throw new Exceptions\Mailer("Transport settings `_object` is not valid.");
        
        foreach($settings as $key => $value) {
            if($key == '_object' || $key == '_handler')
                continue;
            
            $key = 'set'.$key;
            $obj->$key($value);
        }
        
        if(!empty($settings['_handler']))
            $obj = doCall($settings['_handler'], [$obj]);
        
        return $transports[$id] =& $obj;
    }
    
    /**
     *  $settings = [
     *      'id' => 'your-custom-id',
     *      'filePath' => 'file path',
     *      'fileName' => 'fileName to use for this inline image',
     *      'content' => 'image content from memory',
     *      'contentType' => 'file mime type',
     *  ];
     */
    public static function getInlineImage($settings, $id='default') {
        static $images = [];
        
        $id = $settings['id'] ?? $id;
        
        if(isset($images[$id]))
            return $images[$id];
        
        self::_init();
        
        $obj = new \Swift_Image();
        
        if(!empty($settings['filePath']))
            $obj->setFile(new \Swift_ByteStream_FileByteStream($settings['filePath']));
        
        if(!empty($settings['fileName']))
            $obj->setFilename($settings['fileName']);
        
        if(!empty($settings['content']))
            $obj->setBody($settings['content']);
        
        if(!empty($settings['contentType']))
            $obj->setContentType($settings['contentType']);
        
        $obj->internalTrackingId = $id;
        
        return $images[$id] =& $obj;
    }
    
    /**
     *  $settings = [
     *      'id' => 'your-custom-id',
     *      'filePath' => 'file path',
     *      'fileName' => 'fileName to use for this inline image',
     *      'content' => 'image content from memory',
     *      'contentType' => 'file mime type',
     *      'disposition' => 'inline',
     *  ];
     */
    public static function getAttachment($settings, $id='default') {
        static $files = [];
        
        $id = $settings['id'] ?? $id;
        
        if(isset($files[$id]))
            return $files[$id];
        
        self::_init();
        
        $obj = new \Swift_Attachment();
        
        if(!empty($settings['filePath']))
            $obj->setFile(new \Swift_ByteStream_FileByteStream($settings['filePath']));
        
        if(!empty($settings['fileName']))
            $obj->setFilename($settings['fileName']);
        
        if(!empty($settings['content']))
            $obj->setBody($settings['content']);
        
        if(!empty($settings['contentType']))
            $obj->setContentType($settings['contentType']);
        
        if(!empty($settings['disposition']))
            $obj->setDisposition($settings['disposition']);
        
        $obj->internalTrackingId = $id;
        
        return $files[$id] =& $obj;
    }
    
    /**
     *  $config = [
     *       'transport' => 'Swift_Transport object or transport settings', // if not set `defaultEmailTransport` settings will be used
     *       'message' => [
     *           // set{key-name}({value});
     *           'subject' => '',
     *           'from' => [
     *               'fromemail@domain.com' => 'name',
     *           ],
     *           'to' => [
     *               'receiver@domain.org',
     *               'other@domain.org' => 'name',
     *           ],
     *           'cc' => [
     *               'receiver@domain.org',
     *               'other@domain.org' => 'name',
     *           ],
     *           'bcc' => [
     *               'receiver@domain.org',
     *               'other@domain.org' => 'name',
     *           ],
     *           'readReceiptTo' => [
     *               'receiver@domain.org',
     *               'other@domain.or',
     *           ],
     *           'replyTo' => [
     *               'other@domain.org' => 'name',
     *           ],
     *           'returnPath' => 'bounce-handler@domain.com',
     *           'sender' => [
     *               'fromemail@domain.com' => 'name',
     *           ],
     *           'body' => 'Message body <img src="{{inlineImages.logo}}" alt=""/>',
     *           'charset' => '',
     *           'id' => 'message ID',
     *           'contentType' => 'text/html',
     *           
     *           '_handler' => 'yourCallback(Object Swift_Message, Array $inlineImages, Array $attachments): Swift_Message',
     *       ],
     *       'inlineImages' => [
     *          'Array of Swift_Image object or self::getInlineImage() settings',
     *       ],
     *      'attachments' => [
     *          'Array of Swift_Attachment object or self::getAttachment() settings',
     *       ],
     *       'mailer' => [
     *           '_handler' => 'yourCallback(Object Swift_Mailer): Swift_Mailer',
     *       ],
     *   ];
     */
    public static function send($config) {
        self::_init();
        
        $transport = $config['transport'] ?? Core::system()->config['bootstrap']['defaultEmailTransport'];
        
        if((array) $transport === $transport)
            $transport = self::getTransport($transport);
        
        if(!$transport instanceof \Swift_Transport)
            throw new Exceptions\Mailer('Mailer Transport is not valid.');
        
        $inlineImages = [];
        
        if(!empty($config['inlineImages'])) {
            foreach($config['inlineImages'] as $image) {
                if((array) $image === $image)
                    $image = self::getInlineImage($image);
                
                if(!$image instanceof \Swift_Image)
                    throw new Exceptions\Mailer('Inline Image is not an instance of Swift_Image.');
                
                $inlineImages[$image->internalTrackingId] = $image;
            }
        }
        
        $attachments = [];
        
        if(!empty($config['attachments'])) {
            foreach($config['attachments'] as $file) {
                if((array) $file === $file)
                    $file = self::getAttachment($file);
                
                if(!$file instanceof \Swift_Attachment)
                    throw new Exceptions\Mailer('Attachment File is not an instance of Swift_Attachment.');
                
                $attachments[$file->internalTrackingId] = $file;
            }
        }
        
        if(empty($config['message']) || (array) $config['message'] !== $config['message'])
            throw new Exceptions\Mailer('Mailer Message valid settings required.');
        
        $message = new \Swift_Message();
        
        if($attachments) {
            foreach($attachments as $file)
                $message->attach($file);
        }
        
        if($inlineImages && !empty($config['message']['body'])) {
            foreach($inlineImages as $id => $image) {
                $config['message']['body'] = str_replace(
                    "{{inlineImages.$id}}",
                    $message->embed($image),
                    $config['message']['body']
                );
            }
        }
        
        foreach($config['message'] as $key => $value) {
            if($key == '_handler')
                continue;
            
            $key = 'set'.$key;
            $message->$key($value);
        }
        
        if(!empty($config['message']['_handler']))
            $message = doCall($config['message']['_handler'], [$message, $inlineImages, $attachments]);
        
        $mailer = new \Swift_Mailer($transport);
        
        if(!empty($config['mailer']['_handler']))
            $mailer = doCall($config['mailer']['_handler'], [$mailer]);
        
        if($mailer->send($message, $failed))
            return true;
        
        return $failed;
    }
}
/* END OF FILE */
