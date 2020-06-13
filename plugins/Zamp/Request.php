<?php

namespace Zamp;

class Request extends Base {
    const CONTENT_TYPE_URL = 1;
    const CONTENT_TYPE_STRING = 2;
    const CONTENT_TYPE_IMAGE = 3;
    
    // Character set
    public $charset = 'UTF-8';
    
    // Random Hash for protecting URLs
    protected $_xss_hash;
    
    // Generates the XSS hash if needed and returns it
    public function xssHash() {
        if($this->_xss_hash === null)
            $this->_xss_hash = bin2hex(random_bytes(16));
        
        return $this->_xss_hash;
    }
    
    // Do Never Allowed
    protected function _doNeverAllowed($str) {
        $neverAllowedStr = [
            'document.cookie' => '[removed]',
            '(document).cookie' => '[removed]',
            'document.write'  => '[removed]',
            '(document).write'=> '[removed]',
            '.parentNode'     => '[removed]',
            '.innerHTML'      => '[removed]',
            '-moz-binding'    => '[removed]',
            '<!--'            => '&lt;!--',
            '-->'             => '--&gt;',
            '<![CDATA['       => '&lt;![CDATA[',
            '<comment>'       => '&lt;comment&gt;',
            '<%'              => '&lt;&#37;'
        ];
        
        $neverAllowedRegex = [
            'javascript\s*:',
            '(\(?document\)?|\(?window\)?(\.document)?)\.(location|on\w*)',
            'expression\s*(\(|&\#40;)', // CSS and IE
            'vbscript\s*:', // IE, surprise!
            'wscript\s*:', // IE
            'jscript\s*:', // IE
            'vbs\s*:', // IE
            'Redirect\s+30\d',
            "([\"'])?data\s*:[^\\1]*?base64[^\\1]*?,[^\\1]*?\\1?"
        ];
        
        $str = str_replace(array_keys($neverAllowedStr), $neverAllowedStr, $str);
        
        foreach($neverAllowedRegex as $regex)
            $str = preg_replace('#'.$regex.'#is', '[removed]', $str);
        
        return $str;
    }
    
    // Compact Exploded Words. Remove whitespace from things like 'j a v a s c r i p t'
    protected function _compactExplodedWords($matches) {
        return preg_replace('/\s+/s', '', $matches[1]).$matches[2];
    }
    
    /**
     *  XSS Clean
     *  
     *  Sanitizes data so that Cross Site Scripting Hacks can be
     *  prevented.  This method does a fair amount of work but
     *  it is extremely thorough, designed to prevent even the
     *  most obscure XSS attempts.  Nothing is ever 100% foolproof,
     *  of course, but I haven't been able to get anything passed
     *  the filter.
     *  
     *  Note: Should only be used to deal with data upon submission.
     *      It's not something that should be used for general runtime processing.
     */
    public function xssClean($str, $contentType=1) {
        // Is the string an array?
        if((array) $str === $str) {
            while(list($key) = each($str))
                $str[$key] = $this->xssClean($str[$key], $contentType);
            
            return $str;
        }
        
        if($str === null || $str === true || $str === false)
            return $str;
        
        // Remove Invisible Characters
        $str = Security::removeInvisibleCharacters($str);
        
        /*
         * URL Decode
         *
         * Just in case stuff like this is submitted:
         *
         * <a href="http://%77%77%77%2E%67%6F%6F%67%6C%65%2E%63%6F%6D">Google</a>
         *
         * Note: Use rawurldecode() so it does not remove plus signs
         */
        if($contentType === self::CONTENT_TYPE_URL && (stripos($str, '%') !== false)) {
            do {
                $oldstr = $str;
                $str = rawurldecode($str);
                $str = preg_replace_callback('#%(?:\s*[0-9a-f]){2,}#i', [$this, '_urlDecodeSpaces'], $str);
            }
            while($oldstr !== $str);
            
            unset($oldstr);
        }
        
        /*
         * Convert character entities to ASCII
         *
         * This permits our tests below to work reliably.
         * We only convert entities that are within tags since
         * these are the ones that will pose security problems.
         */
        $str = preg_replace_callback("/[^a-z0-9>]+[a-z0-9]+=([\'\"]).*?\\1/si", [$this, '_convertAttribute'], $str);
        $str = preg_replace_callback('/<\w+.*/si', [$this, '_decodeEntity'], $str);
        
        // Remove Invisible Characters Again!
        $str = Security::removeInvisibleCharacters($str);
        
        /*
         * Convert all tabs to spaces
         *
         * This prevents strings like this: ja  vascript
         * NOTE: we deal with spaces between characters later.
         * NOTE: preg_replace was found to be amazingly slow here on
         * large blocks of data, so we use str_replace.
         */
        $str = str_replace("\t", ' ', $str);
        
        // Capture converted string for later comparison
        $converted_string = $str;
        
        // Remove Strings that are never allowed
        $str = $this->_doNeverAllowed($str);
        
        /*
         * Makes PHP tags safe
         *
         * Note: XML tags are inadvertently replaced too:
         *
         * <?xml
         *
         * But it doesn't seem to pose a problem.
         */
        if($contentType === self::CONTENT_TYPE_IMAGE) {
            // Images have a tendency to have the PHP short opening and
            // closing tags every so often so we skip those and only
            // do the long opening tags.
            $str = preg_replace('/<\?(php)/i', '&lt;?\\1', $str);
        }
        else {
            $str = str_replace(['<?', '?'.'>'], ['&lt;?', '?&gt;'], $str);
        }
        
        /*
         * Compact any exploded words
         *
         * This corrects words like:  j a v a s c r i p t
         * These words are compacted back to their correct state.
         */
        $words = [
            'javascript', 'expression', 'vbscript', 'jscript', 'wscript',
            'vbs', 'script', 'base64', 'applet', 'alert', 'document',
            'write', 'cookie', 'window', 'confirm', 'prompt', 'eval'
        ];
        
        foreach($words as $word) {
            $word = implode('\s*', str_split($word)).'\s*';
            
            // We only want to do this when it is followed by a non-word character
            // That way valid stuff like "dealer to" does not become "dealerto"
            $str = preg_replace_callback('#('.substr($word, 0, -3).')(\W)#is', [$this, '_compactExplodedWords'], $str);
        }
        
        /*
         * Remove disallowed Javascript in links or img tags
         * We used to do some version comparisons and use of stripos(),
         * but it is dog slow compared to these simplified non-capturing
         * preg_match(), especially if the pattern exists in the string
         *
         * Note: It was reported that not only space characters, but all in
         * the following pattern can be parsed as separators between a tag name
         * and its attributes: [\d\s"\'`;,\/\=\(\x00\x0B\x09\x0C]
         * ... however, Security::removeInvisibleCharacters() above already strips the
         * hex-encoded ones, so we'll skip them below.
         */
        do {
            $original = $str;
            
            if(preg_match('/<a/i', $str))
                $str = preg_replace_callback('#<a(?:rea)?[^a-z0-9>]+([^>]*?)(?:>|$)#si', [$this, '_jsLinkRemoval'], $str);
            
            if(preg_match('/<img/i', $str))
                $str = preg_replace_callback('#<img[^a-z0-9]+([^>]*?)(?:\s?/?>|$)#si', [$this, '_jsImgRemoval'], $str);
            
            if(preg_match('/script|xss/i', $str))
                $str = preg_replace('#</*(?:script|xss).*?>#si', '[removed]', $str);
        }
        while($original !== $str);
        
        unset($original);
        
        /*
         * Sanitize naughty HTML elements
         *
         * If a tag containing any of the words in the list
         * below is found, the tag gets converted to entities.
         *
         * So this: <blink>
         * Becomes: &lt;blink&gt;
         */
        $pattern = '#'
            .'<((?<slash>/*\s*)((?<tagName>[a-z0-9]+)(?=[^a-z0-9]|$)|.+)' // tag start and name, followed by a non-tag character
            .'[^\s\042\047a-z0-9>/=]*' // a valid attribute character immediately after the tag would count as a separator
            // optional attributes
            .'(?<attributes>(?:[\s\042\047/=]*' // non-attribute characters, excluding > (tag close) for obvious reasons
            .'[^\s\042\047>/=]+' // attribute characters
            // optional attribute-value
                .'(?:\s*=' // attribute-value separator
                    .'(?:[^\s\042\047=><`]+|\s*\042[^\042]*\042|\s*\047[^\047]*\047|\s*(?U:[^\s\042\047=><`]*))' // single, double or non-quoted value
                .')?' // end optional attribute-value group
            .')*)' // end optional attributes group
            .'[^>]*)(?<closeTag>\>)?#isS';
        
        // Note: It would be nice to optimize this for speed, BUT
        //       only matching the naughty elements here results in
        //       false positives and in turn - vulnerabilities!
        do {
            $old_str = $str;
            $str = preg_replace_callback($pattern, [$this, '_sanitizeNaughtyHtml'], $str);
        }
        while($old_str !== $str);
        
        unset($old_str);
        
        /*
         * Sanitize naughty scripting elements
         *
         * Similar to above, only instead of looking for
         * tags it looks for PHP and JavaScript commands
         * that are disallowed. Rather than removing the
         * code, it simply converts the parenthesis to entities
         * rendering the code un-executable.
         *
         * For example: eval('some code')
         * Becomes: eval&#40;'some code'&#41;
         */
        $str = preg_replace(
            '#(alert|prompt|confirm|cmd|passthru|eval|exec|expression|system|fopen|fsockopen|file|file_get_contents|readfile|unlink)(\s*)\((.*?)\)#si',
            '\\1\\2&#40;\\3&#41;',
            $str
        );
        
        $str = preg_replace(
            '#(alert|prompt|confirm|cmd|passthru|eval|exec|expression|system|fopen|fsockopen|file|file_get_contents|readfile|unlink)(\s*)`(.*?)`#si',
            '\\1\\2&#96;\\3&#96;',
            $str
        );
        
        // Final clean up
        // This adds a bit of extra precaution in case
        // something got through the above filters
        $str = $this->_doNeverAllowed($str);
        
        /*
         * Images are Handled in a Special Way
         * - Essentially, we want to know that after all of the character
         * conversion is done whether any unwanted, likely XSS, code was found.
         * If not, we return TRUE, as the image is clean.
         * However, if the string post-conversion does not matched the
         * string post-removal of XSS, then it fails, as there was unwanted XSS
         * code found and removed/changed during processing.
         */
        if($contentType === self::CONTENT_TYPE_IMAGE)
            return $str === $converted_string;
        
        return $str;
    }
    
    // Sanitize Naughty HTML
    protected function _sanitizeNaughtyHtml($matches) {
        static $naughty_tags = [
            'alert', 'area', 'prompt', 'confirm', 'applet', 'audio', 'basefont', 'base', 'behavior', 'bgsound',
            'blink', 'body', 'embed', 'expression', 'form', 'frameset', 'frame', 'head', 'html', 'ilayer',
            'iframe', 'input', 'button', 'select', 'isindex', 'layer', 'link', 'meta', 'keygen', 'object',
            'plaintext', 'style', 'script', 'textarea', 'title', 'math', 'video', 'svg', 'xml', 'xss'
        ];
        
        static $evil_attributes = [
            'on\w+', 'style', 'xmlns', 'formaction', 'form', 'xlink:href', 'FSCommand', 'seekSegmentTime'
        ];
        
        // First, escape unclosed tags
        if(empty($matches['closeTag']))
            return '&lt;'.$matches[1];
        
        // Is the element that we caught naughty? If so, escape it
        elseif(in_array(strtolower($matches['tagName']), $naughty_tags, true))
            return '&lt;'.$matches[1].'&gt;';
        
        // For other tags, see if their attributes are "evil" and strip those
        elseif(isset($matches['attributes'])) {
            // We'll store the already fitlered attributes here
            $attributes = [];
            
            // Attribute-catching pattern
            $attributes_pattern = '#'
                .'(?<name>[^\s\042\047>/=]+)' // attribute characters
                // optional attribute-value
                .'(?:\s*=(?<value>[^\s\042\047=><`]+|\s*\042[^\042]*\042|\s*\047[^\047]*\047|\s*(?U:[^\s\042\047=><`]*)))' // attribute-value separator
                .'#i';
            
            // Blacklist pattern for evil attribute names
            $is_evil_pattern = '#^('.implode('|', $evil_attributes).')$#i';
            
            // Each iteration filters a single attribute
            do {
                // Strip any non-alpha characters that may preceed an attribute.
                // Browsers often parse these incorrectly and that has been a
                // of numerous XSS issues we've had.
                $matches['attributes'] = preg_replace('#^[^a-z]+#i', '', $matches['attributes']);
                
                if(!preg_match($attributes_pattern, $matches['attributes'], $attribute, PREG_OFFSET_CAPTURE)) {
                    // No (valid) attribute found? Discard everything else inside the tag
                    break;
                }
                
                if(
                    // Is it indeed an "evil" attribute?
                    preg_match($is_evil_pattern, $attribute['name'][0])
                    // Or does it have an equals sign, but no value and not quoted? Strip that too!
                    || (trim($attribute['value'][0]) === '')
                ) {
                    $attributes[] = 'xss=removed';
                }
                else {
                    $attributes[] = $attribute[0][0];
                }
                
                $matches['attributes'] = substr($matches['attributes'], $attribute[0][1] + strlen($attribute[0][0]));
            }
            while($matches['attributes'] !== '');
            
            $attributes = empty($attributes)
                ? ''
                : ' '.implode(' ', $attributes);
            
            return '<'.$matches['slash'].$matches['tagName'].$attributes.'>';
        }
        
        return $matches[0];
    }
    
    // HTML Entity Decode Callback
    protected function _decodeEntity($match) {
        // Protect GET variables in URLs
        // 901119URL5918AMP18930PROTECT8198
        $match = preg_replace('|\&([a-z\_0-9\-]+)\=([a-z\_0-9\-/]+)|i', $this->xssHash().'\\1=\\2', $match[0]);
        
        // Decode, then un-protect URL GET vars
        return str_replace(
            $this->xssHash(),
            '&',
            $this->entityDecode($match, $this->charset)
        );
    }
    
    // URL-decode taking spaces into account
    protected function _urlDecodeSpaces($matches) {
        $input = $matches[0];
        $nospaces = preg_replace('#\s+#', '', $input);
        
        return ($nospaces === $input) ?$input :rawurldecode($nospaces);
    }
    
    /**
     *  HTML Entities Decode
     *  
     *  A replacement for html_entity_decode()
     *  
     *  The reason we are not using html_entity_decode() by itself is because
     *  while it is not technically correct to leave out the semicolon
     *  at the end of an entity most browsers will still interpret the entity
     *  correctly. html_entity_decode() does not convert entities without
     *  semicolons, so we are left with our own little solution here. Bummer.
     */
    public function entityDecode($str, $charset=null) {
        if(strpos($str, '&') === false)
            return $str;
        
        static $_entities;
        
        if(!isset($charset))
            $charset = $this->charset;
        
        $flag = ENT_COMPAT | ENT_HTML5;
        
        if(!isset($_entities))
            $_entities = array_map('strtolower', get_html_translation_table(HTML_ENTITIES, $flag, $charset));
        
        do {
            $str_compare = $str;
            
            // Decode standard entities, avoiding false positives
            if(preg_match_all('/&[a-z]{2,}(?![a-z;])/i', $str, $matches)) {
                $replace = [];
                $matches = array_unique(array_map('strtolower', $matches[0]));
                
                foreach($matches as &$match) {
                    if(($char = array_search($match.';', $_entities, true)) !== false) {
                        $replace[$match] = $char;
                    }
                }
                
                $str = str_replace(array_keys($replace), array_values($replace), $str);
            }
            
            // Decode numeric & UTF16 two byte entities
            $str = html_entity_decode(
                preg_replace('/(&#(?:x0*[0-9a-f]{2,5}(?![0-9a-f;])|(?:0*\d{2,4}(?![0-9;]))))/iS', '$1;', $str),
                $flag,
                $charset
            );
        }
        while($str_compare !== $str);
        
        return $str;
    }
    
    // Filters tag attributes for consistency and safety
    protected function _filterAttributes($str) {
        $out = '';
        
        if(preg_match_all('#\s*[a-z\-]+\s*=\s*(\042|\047)([^\\1]*?)\\1#is', $str, $matches)) {
            foreach($matches[0] as $match) {
                $out .= preg_replace('#/\*.*?\*/#s', '', $match);
            }
        }
        
        return $out;
    }
    
    /**
     *  JS Link Removal
     *  
     *  This limits the PCRE backtracks, making it more performance friendly
     *  and prevents PREG_BACKTRACK_LIMIT_ERROR from being triggered in
     *  PHP 5.2+ on link-heavy strings.
     */
    protected function _jsLinkRemoval($match) {
        return str_replace(
            $match[1],
            preg_replace(
                '#href=.*?(?:(?:alert|prompt|confirm)(?:\(|&\#40;)|javascript:|livescript:|mocha:|charset=|window\.|document\.|\.cookie|<script|<xss|d\s*a\s*t\s*a\s*:)#si',
                '',
                $this->_filterAttributes($match[1])
            ),
            $match[0]
        );
    }
    
    /**
     *  JS Image Removal
     *  
     *  This limits the PCRE backtracks, making it more performance friendly
     *  and prevents PREG_BACKTRACK_LIMIT_ERROR from being triggered in
     *  PHP 5.2+ on image tag heavy strings.
     */
    protected function _jsImgRemoval($match) {
        return str_replace(
            $match[1],
            preg_replace(
                '#src=.*?(?:(?:alert|prompt|confirm|eval)(?:\(|&\#40;)|javascript:|livescript:|mocha:|charset=|window\.|document\.|\.cookie|<script|<xss|base64\s*,)#si',
                '',
                $this->_filterAttributes($match[1])
            ),
            $match[0]
        );
    }
    
    // Attribute Conversion
    protected function _convertAttribute($match) {
        return str_replace(array('>', '<', '\\'), array('&gt;', '&lt;', '\\\\'), $match[0]);
    }
    
    // Fetch the IP Address
    public function ipAddress(&$proxyIp=null) {
        if(isset($this->ipAddress)) {
            $proxyIp = $this->ipAddress['proxyIp'];
            return $this->ipAddress['realIp'];
        }
        
        $ipSourceList = [
            'HTTP_X_FORWARDED_FOR',
            'HTTP_CLIENT_IP',
            'HTTP_X_CLIENT_IP',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_X_FORWARDED',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        ];
        
        foreach($ipSourceList as $ip) {
            if(isset($_SERVER[$ip])) {
                $matched = $ip;
                $realIp = $_SERVER[$ip];
                break;
            }
        }
        
        $proxyIp = $_SERVER['REMOTE_ADDR'];
        
        if($realIp == $proxyIp)
            $proxyIp = null;
        else {
            $realIp = str_replace(';', ',', $realIp);
            $realIp = explode(',', $realIp);
            
            $temp = [];
            
            foreach($realIp as $ip) {
                $ip = trim($ip);
                
                if(!$ip || !filter_var($ip, FILTER_VALIDATE_IP))
                    continue;
                
                $temp[$ip] = 1;
            }
            
            $realIp = key($temp);
            unset($temp[$realIp]);
            
            if($temp) {
                $temp[$proxyIp] = 1;
                $proxyIp = implode(', ', array_keys($temp));
            }
        }
        
        $this->ipAddress = [
            'realIp' => $realIp,
            'proxyIp' => $proxyIp
        ];
        
        return $realIp;
    }
    
    // Is ajax request
    public function isAjax() {
        return (
            isset($_SERVER['HTTP_X_REQUESTED_WITH'])
                &&
            $_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest'
        );
    }
    
    // Is request from CLI
    public function isCli() {
        return NEXT_LINE != '<br/>';
    }
    
    // Is post request?
    public function isPost() {
        return $_SERVER['REQUEST_METHOD'] === 'POST';
    }
    
    // Return clients user agent information
    public function userAgent() {
        if(isset($this->userAgent))
            return $this->userAgent;
        
        $this->userAgent = isset($_SERVER['HTTP_USER_AGENT']) ?$this->xssClean($_SERVER['HTTP_USER_AGENT']) :'';
        
        return $this->userAgent;
    }
    
    // This is a helper function to retrieve values from global arrays
    private function _grubGlobalArray(&$array, $name='', $xssClean=false) {
        if(!isset($array[$name]))
            return;
        
        if($xssClean)
            return $this->arrayXssClean($array[$name], false);
        
        return $array[$name];
    }
    
    // Apply XSS filter for parsed input
    public function arrayXssClean($data, $contentType=1) {
        if((array) $data === $data) {
            $new_data = [];
            
            foreach($data as $key => $value)
                $new_data[$key] = ((array) $value === $value) ?$this->arrayXssClean($value, $contentType) :$this->xssClean($value, $contentType);
            
            return $new_data;
        }
        else
            return $this->xssClean($data, $contentType);
    }
    
    // Fetch an item from the COOKIE array
    public function cookie($name='', $xssClean=true) {
        if($name) {
            if(!isset($_COOKIE[$name]))
                return null;
            
            if(!$xssClean)
                return $_COOKIE[$name];
            
            if(!isset($this->cookie_data[$name]))
                $this->cookie_data[$name] = $this->_grubGlobalArray($_COOKIE, $name, $xssClean);
            
            return $this->cookie_data[$name];
        }
        else {
            if(!$xssClean)
                return $_COOKIE;
            
            $this->cookie_data = $this->arrayXssClean($_COOKIE);
            
            return $this->cookie_data;
        }
    }
    
    // Fetch an item from the POST array
    public function post($name='', $xssClean=true) {
        if($name) {
            if(!isset($_POST[$name]))
                return null;
            
            if(!$xssClean)
                return $_POST[$name];
            
            if(!isset($this->post_data[$name]))
                $this->post_data[$name] = $this->_grubGlobalArray($_POST, $name, $xssClean);
            
            return $this->post_data[$name];
        }
        else {
            if(!$xssClean)
                return $_POST;
            
            $this->post_data = $this->arrayXssClean($_POST);
            
            return $this->post_data;
        }
    }
    
    private function _server($name) {
        $replaceBack = [];
        
        if(
            $name == 'REQUEST_URI'
                ||
            $name == 'QUERY_STRING'
                ||
            $name == 'HTTP_REFERER'
        ) {
            $replaceBack = [
                '%26' => '~26', // &
                '%2B' => '~2B', // +
                '%3F' => '~3F', // ?
                '%2F' => '~2F', // /
                '%7E' => '~7E', // ~
                '%3A' => '~3A', // :
                '%5C' => '~5C', // \
                '%3D' => '~3D', // =
                '%40' => '~40', // @
                '%23' => '~23', // #
            ];
            
            $replaceBack = [array_keys($replaceBack), $replaceBack];
        }
        
        $serverData = $_SERVER[$name];
        
        if($serverData !== null) {
            $patterns = [
                '~([\?\&]?)%0[aAdD][^&]*~' => '\\1',
                '~\?&+~' => '?',
                '~&{2,}~' => '&',
            ];
            
            $serverData = preg_replace(array_keys($patterns), $patterns, $serverData);
            
            if($replaceBack)
                $serverData = str_replace($replaceBack[0], $replaceBack[1], $serverData);
            
            $serverData = $this->xssClean($serverData, false);
            
            if($replaceBack)
                $serverData = str_replace($replaceBack[1], $replaceBack[0], $serverData);
        }
        
        return $this->server_data[$name] = $serverData;
    }
    
    // Fetch an item from the SERVER array
    public function server($name='', $xssClean=true) {
        if($name) {
            if(!isset($_SERVER[$name]))
                return null;
            
            if(!$xssClean)
                return $_SERVER[$name];
            
            if(isset($this->server_data[$name]))
                return $this->server_data[$name];
            
            return $this->_server($name);
        }
        else {
            if(!$xssClean)
                return $_SERVER;
            
            foreach($_SERVER as $name => $value)
                $this->_server($name);
            
            return $this->server_data;
        }
    }
    
    // Fetch an item from the GET array
    public function get($name=null) {
        $query = Core::system()->bootInfo('query');
        
        if($name !== null)
            return $query[$name] ?? null;
        else
            return $query;
    }
    
    // Get request body
    public function body() {
        return file_get_contents('php://input');
    }
    
    // Set get method data dynamically
    public function setGet($name, $value) {
        $query = $this->get();
        $query[$name] = $value;
        
        Core::system()->bootInfoSet('query', $query);
    }
    
    // Fetch an item from either the POST or GET
    public function getParam($name='', $xssClean=true) {
        if(!isset($_POST[$name]))
            return $this->get($name);
        else
            return $this->post($name, $xssClean);
    }
    
    // Get get query url with or without numeric index
    public function getQueryUrl($includeNumericIndex=true, $argSeparator="&", $getData=[]) {
        $getQueryUrl = '';
        
        if(!$getData)
            $getData = $this->get();
        
        if((array) $getData === $getData) {
            ksort($getData, SORT_STRING);
            
            $queryParams = [];
            
            foreach($getData as $k => $v) {
                if(!is_numeric($k))
                    $queryParams[$k] = $v;
                elseif($includeNumericIndex)
                    $getQueryUrl .= "$v/";
            }
            
            $getQueryUrl = rtrim($getQueryUrl, '/');
            
            if($queryParams)
                $getQueryUrl .= '?'.http_build_query($queryParams, '', $argSeparator);
        }
        
        return $getQueryUrl;
    }
}
/* END OF FILE */
