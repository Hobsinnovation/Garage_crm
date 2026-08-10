<?php

use Simcify\Auth;
use Simcify\Container;
use Simcify\Database;
use Simcify\FS;
use Simcify\Config;
use Simcify\Router;
use Simcify\Session;
use Simcify\Str;

if(! function_exists('asset')) {
    /**
     * Generate a valid asset url
     * 
     * @param   string  $url
     * @return  mixed
     */
    function asset($url) {
        // Use APP_URL as the canonical base. Application.php automatically
        // maps APP_URL to the active XAMPP sub-folder when running locally.
        $base = rtrim((string) env('APP_URL'), '/');
        $path = ltrim((string) $url, '/');
        $separator = (strpos($path, '?') !== false) ? '&' : '?';
        $version = (string) env('APP_VERSION');
        $local = dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, preg_replace('/\?.*$/', '', $path));
        if (is_file($local)) {
            $version .= '-' . filemtime($local);
        }
        return $base . '/' . $path . $separator . 'ver=' . rawurlencode($version);
    }
}

if(! function_exists('app_url')) {
    /**
     * Build an application URL without relying on SimpleRouter's controller
     * callback lookup. This is especially important for PDF endpoints and
     * XAMPP sub-folder installs.
     */
    function app_url($path = '') {
        $base = rtrim((string) env('APP_URL'), '/');
        $path = trim((string) $path, '/');
        return $path === '' ? $base . '/' : $base . '/' . $path . '/';
    }
}

if(! function_exists('document_asset')) {
    /**
     * Resolve a public asset to a local filesystem path when possible.
     * TCPDF is much more reliable on XAMPP with local files than with a
     * loopback http://localhost URL. Falls back to APP_URL in production.
     */
    function document_asset($path) {
        $path = ltrim((string) $path, '/');
        $root = dirname(__DIR__);
        $local = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);
        if (is_file($local)) {
            return $local;
        }
        return rtrim((string) env('APP_URL'), '/') . '/' . $path;
    }
}

if(! function_exists('pdf_inline_response')) {
    /**
     * Send a TCPDF document as a clean inline PDF response.
     * This strips PHP warnings/notices from the PDF byte stream so PDF.js
     * never receives HTML before the %PDF header on local XAMPP setups.
     */
    function pdf_inline_response($pdf, $filename = 'document.pdf') {
        $data = $pdf->Output('', 'S');

        // Validate the PDF before sending it. This prevents PDF.js from
        // reporting the vague "Invalid PDF structure" when PHP actually
        // produced an error page or an incomplete document.
        if (!is_string($data) || strncmp($data, '%PDF-', 5) !== 0 || strpos(substr($data, -2048), '%%EOF') === false) {
            throw new \RuntimeException('TCPDF did not produce a complete PDF byte stream.');
        }

        // Discard everything produced earlier in the request (warnings, BOM,
        // debug output, template whitespace). The response must begin at %PDF-.
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }

        // Some Windows/XAMPP builds can apply zlib compression after PHP has
        // calculated a Content-Length. That can truncate/corrupt inline PDFs.
        // Disable transport compression and deliberately let the web server
        // stream the byte count instead of forcing Content-Length here.
        @ini_set('zlib.output_compression', '0');
        if (function_exists('apache_setenv')) { @apache_setenv('no-gzip', '1'); }

        if (!headers_sent()) {
            http_response_code(200);
            if (function_exists('header_remove')) {
                @header_remove('Content-Length');
                @header_remove('Content-Encoding');
            }
            header('Content-Type: application/pdf');
            header('Content-Disposition: inline; filename="' . str_replace('"', '', $filename) . '"');
            header('Content-Transfer-Encoding: binary');
            header('Accept-Ranges: bytes');
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            header('Pragma: no-cache');
            header('X-Content-Type-Options: nosniff');
        }

        echo $data;
        if (function_exists('flush')) { @flush(); }
        exit;
    }
}

if(! function_exists('pdf_failure_response')) {
    /**
     * Return a controlled non-PDF error and log the real exception.
     * render.js detects this response before passing anything to PDF.js.
     */
    function pdf_failure_response($context, $error, $status = 500) {
        $reference = 'PDF-' . date('Ymd-His') . '-' . substr(md5(uniqid('', true)), 0, 6);
        $root = dirname(__DIR__);
        $logDir = $root . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'logs';
        if (!is_dir($logDir)) { @mkdir($logDir, 0775, true); }
        $message = '[' . date('c') . '] [' . $reference . '] ' . $context . ' :: ' . get_class($error) . ': ' . $error->getMessage()
                 . ' in ' . $error->getFile() . ':' . $error->getLine() . PHP_EOL . $error->getTraceAsString() . PHP_EOL . PHP_EOL;
        @file_put_contents($logDir . DIRECTORY_SEPARATOR . 'pdf-errors.log', $message, FILE_APPEND);

        while (ob_get_level() > 0) { @ob_end_clean(); }
        http_response_code((int) $status);
        if (!headers_sent()) {
            header('Content-Type: text/plain; charset=UTF-8');
            header('Cache-Control: no-store');
            header('X-Content-Type-Options: nosniff');
        }

        $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
        $local = strpos($host, 'localhost') === 0 || strpos($host, '127.0.0.1') === 0 || $host === '';
        echo 'PDF generation failed. Reference: ' . $reference;
        if ($local) {
            echo "\n" . $error->getMessage();
            echo "\nLog: uploads/logs/pdf-errors.log";
        }
        exit;
    }
}

if(! function_exists('user')) {
    /**
     * Generate current user details
     * 
     * @param   string  $url
     * @return  mixed
     */
    function user() {
        return $user = Auth::user();
    }
}

if (! function_exists('money')) {
    /**
     * Return formated money with currency.
     *
     * @param  int|decimal  $number
     * @return string
     */
    function money($number, $currency = "KES")
    {
        if ($currency == "subscription_currency") {
            $currency = env("SUBSCRIPTION_CURRENCY");
            return moneyFormat($number, $currency); 
        }else{
            return moneyFormat($number, $currency); 
        }
    }
}
if (! function_exists('currency')) {
    /**
     * Return user currency symbol.
     *
     * @param  int|decimal  $number
     * @return string
     */
    function currency($currency)
    {
        $currency = new Gerardojbaez\Money\Currency($currency);
        return $currency->getSymbol(); 
    }
}

if(! function_exists('back')) {
    /**
     * Redirect to the previous page
     * 
     * @return  void
     */
    function back() {
        return response()->redirect(request()->getReferrer());
    }
}

if(! function_exists('carmake')) {
    /**
     * Get car makes name
     * 
     * @return  string
     */
    function carmake($makeid) {
        if ((is_int($makeid) || (is_string($makeid) && ctype_digit($makeid))) && (int) $makeid > 0) {
            $make = Database::table('makes')->where('id', $makeid)->first();
            if (!empty($make)) {
                return $make->name;
            }else{
                return $makeid;
            }
        }

        return $makeid;
    }
}

if(! function_exists('carmodel')) {
    /**
     * Get car model name
     * 
     * @return  string
     */
    function carmodel($modelid) {
        if ((is_int($modelid) || (is_string($modelid) && ctype_digit($modelid))) && (int) $modelid > 0) {
            $model = Database::table('models')->where('id', $modelid)->first();
            if (!empty($model)) {
                return $model->name;
            }else{
                return $modelid;
            }
        }

        return $modelid;
    }
}


if(! function_exists('config')) {
    /**
     * Get a config value
     * 
     * @param   string  $str
     * @param   mixed   $value
     * @return  mixed
     */
    function config($str, $value = null) {
        if (is_null($value)) {
            return Config::get($str);
        }else {
            return Config::set($str, $value);
        }
        
    }
}

if(! function_exists('container')) {
    /**
     * Get/Set a config value
     * 
     * @param   string  $key
     * @param   mixed   $value
     * @return  mixed
     */
    function container($key, $value = null) {
        $container = Container::getInstance($key);
        if ( is_null($value) ) {
            return $container->get($key);
        } else {
            return $container->set($key, $value);
        }
    }
}

if (! function_exists('cookie')) {
    /**
     * Get/Set a cookie
     * 
     * @param   string  $key
     * @param   mixed   $value
     * @param   float   $days
     * @return  mixed
     */
    function cookie($key, $value = null, $days = 1) {
        if ( is_null($value) ) {
            return isset($_COOKIE[$key]) ? $_COOKIE[$key] : null;
        } else {
            return setcookie($key, $value, time() + (86400 * $days), '/');
        }
    }
}
if (! function_exists('env')) {
    /**
     * Gets the value of an environment variable. Supports boolean, empty and null.
     *
     * @param  string  $key
     * @param  mixed   $default
     * @return mixed
     */
    function env($key, $default = null) {
        $value = getenv($key);

        if ($value === false) {
            return value($default);
        }

        switch ( strtolower($value) ) {
            case 'true':
            case '(true)':
                return true;
            case 'false':
            case '(false)':
                return false;
            case 'empty':
            case '(empty)':
                return '';
            case 'null':
            case '(null)':
                return;
        }

        if ( strlen($value) > 1 && Str::startsWith($value, '"') && Str::endsWith($value, '"') ) {
            return substr($value, 1, -1);
        }

        return $value;
    }
}

if (! function_exists('hash_compare')) {
    /**
     * Compare two string hashes
     * 
     * @param   string  $a
     * @param   string  $a
     * @return  boolean
     */
    function hash_compare($a, $b) {
        if ( !is_string($a) || !is_string($b) ) { 
            return false; 
        } 
        
        $len = strlen($a); 
        if ($len !== strlen($b)) { 
            return false; 
        } 

        $status = 0; 
        for ($i = 0; $i < $len; $i++) { 
            $status |= ord($a[$i]) ^ ord($b[$i]); 
        } 
        return $status === 0; 
    }
}

if (! function_exists('session')) {
    /**
     * Get the current session
     * 
     * @param   mixed   $key
     * @param   mixed   $value
     * @return  mixed
     */
    function session($key = null, $value = null) {
        $session = container(Session::class);
        if ( is_null($key) ) {
            return $session;
        } else if ( is_null($value) ) {
            return $session->get($key);
        } else {
            $session->put($key, $value);
        }        
    }
}

if (! function_exists('responder')) {
    /**
     * Return Json response
     * 
     * @param   mixed   $key
     * @param   mixed   $value
     * @return  mixed
     */
    function responder($status, $title, $message, $callback = null, $notify = true, $notifyType = null, $callbackTime = "onconfirm") {
        $response = array(
                "status" => $status,
                "title" => $title,
                "message" => $message
            );
        if (!empty($callback)) {
            $response["callback"] = $callback;
        }
        if (!$notify) {
            $response["notify"] = false;
        }
        if (isset($notifyType)) {
            $response["notifyType"] = $notifyType;
        }
        if ($callbackTime == "instant") {
            $response["callbackTime"] = $callbackTime;
        }
        return $response;     
    }
}

if (! function_exists('escape')) {
    /**
     * Return an escaped string
     * 
     * @param   string   $string
     * @return  string
     */
    function escape($string) {
        if (!empty($string)) {
            $string = mb_convert_encoding($string, 'UTF-8', 'UTF-8');
            $string = htmlentities($string, ENT_QUOTES);
            return $string;
        }else{
            return "";
        }
    }
}


if (! function_exists('timezoned')) {
    /**
     * Returns a local time on a specific timezone
     * 
     * @param   string   $date
     * @param   string   $timezone
     * @return  string
     */
    function timezoned($date, $timezone = "Africa/Nairobi") {
        $dateTime = new \DateTime($date, new \DateTimeZone('GMT'));
        $dateTime->setTimezone(new \DateTimeZone($timezone));
        $timezoned = $dateTime->format('Y-m-d H:i:s');
        
        return $timezoned;     
    }
}

if (! function_exists('timeAgo')) {
    /**
     * Return time elapsed.
     *
     * @param  string  $time_ago
     * @return string
     */
    function timeAgo($time_ago){
        // return $time_ago;
        
        $cur_time   = time();
        $time_elapsed   = $cur_time - $time_ago;
        $seconds    = $time_elapsed ;
        $minutes    = round($time_elapsed / 60 );
        $hours      = round($time_elapsed / 3600);
        $days       = round($time_elapsed / 86400 );
        $weeks      = round($time_elapsed / 604800);
        $months     = round($time_elapsed / 2600640 );
        $years      = round($time_elapsed / 31207680 );
        // Seconds
        if($seconds <= 60){
            return "$seconds seconds ago";
        }
        //Minutes
        else if($minutes <=60){
            if($minutes==1){
                return "one minute ago";
            }
            else{
                return "$minutes minutes ago";
            }
        }
        //Hours
        else if($hours <=24){
            if($hours==1){
                return "an hour ago";
            }else{
                return "$hours hours ago";
            }
        }
        //Days
        else if($days <= 7){
            if($days==1){
                return "yesterday";
            }else{
                return "$days days ago";
            }
        }
        //Weeks
        else if($weeks <= 4.3){
            if($weeks==1){
                return "a week ago";
            }else{
                return "$weeks weeks ago";
            }
        }
        //Months
        else if($months <=12){
            if($months==1){
                return "a month ago";
            }else{
                return "$months months ago";
            }
        }
        //Years
        else{
            if($years==1){
                return "one year ago";
            }else{
                return "$years years ago";
            }
        }
    }
}

if (! function_exists('timeLeft')) {

        /**
     * Usage: App_Sandbox_String_Util::getDateDiff();
     * @param int $date timestamp
     * @param bool $hr human readable. e.g. 1 year(s) 2 day(s)
     */
    function timeLeft($date, $hr = 1) {
        $now = time(); // or your date as well
        $datediff = $date - $now;
        $days = floor( $datediff / ( 3600 * 24 ) );

        if ($days < 1) {
            return "0 days";
        }

        $label = '';

        if ($hr) {
            if ($days >= 365) { // over a year
                $years = floor($days / 365);
                if ($years == 1) {
                    $label .= $years . ' Year';
                }elseif ($years > 1) {
                    $label .= $years . ' Years';
                }
                $days -= 365 * $years;
            }

            if ($days) {
                $months = floor( $days / 30 );
                if ($months == 1) {
                    $label .= ' '.$months . ' Month';
                }elseif ($months > 1) {
                    $label .= ' '.$months . ' Months';
                }
                $days -= 30 * $months;
            }

            if ($days) {
                if ($days == 1) {
                    $label .= ' '.$days . ' day';
                }elseif ($days > 1) {
                    $label .= ' '.$days . ' days';
                }
            }
        } else {
            $label = $days;
        }

        return $label;
    }
}

if (! function_exists('value')) {
    /**
     * Return the default value of the given value.
     *
     * @param  mixed  $value
     * @return mixed
     */
    function value($value)
    {
        return $value instanceof Closure ? $value() : $value;
    }
}

if (! function_exists('view')) {
    /**
     * Return a html page view
     * 
     * @param   string  $name
     * @param   array   $data
     * @return  string
     */
    function view($name = 'errors.404', array $data = []) {
        $general_path = FS::disk('views')->path(str_replace('.', '/', $name));
        $HTML_path = "{$general_path}.html";
        $PHP_path = "{$general_path}.php";
        $text_path = "{$general_path}.txt";

        ob_start();
        if (file_exists($PHP_path)) {
            $general_path = $PHP_path;
        } else if (file_exists($HTML_path)) {
            $general_path = $HTML_path;
        } else if (file_exists($text_path)) {
            $general_path = $text_path;
        } else {
            $general_path = FS::path('errors/404.php');
        }
        include $general_path;

        $search_n_replace = [
            '/{{/'                      => '<?= ',
            '/}}/'                      => '; ?>',
            '/\@include\s*\((.*)\)/'         => '<?= view( $1, $s_v_data ); ?>',
            '/\@for(\w*)\s*(\(.*\))/'   => '<?php for$1 $2 { ?>',
            '/\@if\s*(\(.*\))/'         => '<?php if $1 { ?>',
            '/\@elseif\s*(\(.*\))/'     => '<?php } else if $1 { ?>',
            '/\@else/'                  => '<?php } else { ?>',
            '/\@end\w+/'                => '<?php } ?>',
        ];
        $globals = ['<?php global $s_v_data'];
        global $s_v_data;
        $s_v_data = $data;
        foreach($data as $var => $val) {
            global ${$var};
            ${$var} = $val;
            array_push($globals, ", \${$var}");
        }
        array_push($globals, "; ?>\n");
        $view = preg_replace(
            array_keys($search_n_replace),
            array_values($search_n_replace),
            implode('', $globals) . ob_get_contents() . "\n<?php return;\n"
        );
        ob_clean();
        $cache_filename = 'framework/views/' . md5($name) . '.php';
        FS::disk('storage')->save($cache_filename, $view);
        include FS::path($cache_filename);

        return ob_get_clean();
    }
}


if (! function_exists('__')) {
    /**
     * Get the translated value of the set language
     * 
     * @param   string  $name
     * @return  string
     */
    function __($name) {
        $dot_keys = explode('.', $name);
        $locale = config('app.locale.default');
        $PHP_path = config("filesystem.disk.lang")."/{$locale}/{$dot_keys[0]}.php";
        if (file_exists($PHP_path)) {
            $value = include $PHP_path;
            if(count($dot_keys) > 1) {
                for($x = 1; $x < count($dot_keys); $x++) {
                    $value = $value[$dot_keys[$x]];
                }
            }
            return $value;
        } else {
            return $name;
        }
    }
}
