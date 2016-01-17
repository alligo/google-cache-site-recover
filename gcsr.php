#!/usr/bin/php

<?php

class GoogleCacheSiteRecover
{

    /**
     * Base. Use if your list just have site path, not full url
     *
     * @var String
     */
    protected $base_cache = 'http://webcache.googleusercontent.com/search?q=cache:';
    protected $base_site = '';
    protected $debug_level = 1;

    /**
     * Maximum consecutive tentatives to ask google cache for page
     *
     * @var Integer
     */
    protected $error_count_max = 5;

    /**
     * How many consecutive server errors we have now?
     *
     * @var Integer
     */
    protected $error_count_now = 0;
    protected $save_path = '';
    protected $force_html_sufix = true;
    protected $google_cache_sufix = '&hl=pt-BR&ct=clnk&gl=br&client=ubuntu';

    /**
     * Set to false to disable get HTML from google cache and get from site itself
     *
     * @var  Integer 
     */
    protected $google_cache_use = true;
    protected $ignore = [];
    protected $ignore_file = 'ignore.txt';
    protected $info_file_processed = 'gcsr_processed.txt';
    protected $info_file_lasttry = 'gcsr_lastitem.txt';
    protected $info_file_doneok = 'gcsr_doneok.txt';
    protected $info_file_done404 = 'gcsr_done404.txt';
    protected $info_file_error = 'gcsr_error.txt';
    protected $info_file_raw = 'gcsr_raw.html';

    /**
     * Fake user agent. Default curl agent will get you banned
     *
     * @var String
     */
    protected $user_agent = 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Ubuntu Chromium/47.0.2526.73 Chrome/47.0.2526.73 Safari/537.36';

    /**
     * Last status code from Google Cache
     *
     * @var  Integer
     */
    protected $status_code = -1;
    protected $url_file = null;
    protected $url_stack = [];
    protected $request_count = 0;
    protected $start_time = 0;

    /**
     * Time, in seconds, to wait if Google think that this is an automated
     * test
     *
     * @var Integer
     */
    protected $wait_error = 307;

    /**
     * Not implemented
     *
     * @var Integer
     */
    protected $wait_myhost = 1;

    /**
     * Minimum time, in seconds, to take page from google cache
     *
     * @var Integer
     */
    protected $wait_min = 63;

    /**
     * Maximum time, in seconds, to take page from google cache
     *
     * @var Integer
     */
    protected $wait_max = 70;
    protected $proxy_file = 'proxy.txt';
    protected $proxy_enabled = false;
    protected $proxy_list = 'proxy.txt';

    /**
     * Initialize values
     */
    function __construct()
    {
        $this->save_path = getcwd() . '/output';
    }

    /**
     * Clear Google Cache header
     *
     * @param   String   $string
     * @return  String
     */
    protected function clearGoogleCacheHeader($string)
    {

        if (strpos($string, 'style="position:relative;">') === false) {
            echo gmdate("Y-m-d\TH:i:s\Z") . ": \033[31mERROR\033[37m" . ' clearGoogleCacheHeader: method is outdated. Please update me!';
            return $string;
        } else {
            $parts = explode('style="position:relative;">', $string);
            $good_parts = array_splice($parts, 1);
            $html_string = implode('', $good_parts);

            // This part needs more testing, Force UTF8 encoding for google cache pages
            if (strpos('charset=utf-8', $html_string) === false && strpos('charset=UTF-8', $html_string) === false) {
                $html_string = str_replace('<head>', '<head><meta http-equiv="Content-Type" content="text/html; charset=UTF-8">', $html_string);
            }

            echo gmdate("Y-m-d\TH:i:s\Z") . ' DEBUG clearGoogleCacheHeader: cleared';
            return $html_string;
        }
    }

    /**
     * Execute
     */
    public function execute()
    {
        //$this->debug_level && print_r($this);
        $this->start_time = time();

        echo gmdate("Y-m-d\TH:i:s\Z") . ': Google Cache Site Recover version 0.2 started now' . PHP_EOL;

        if (is_file($this->url_file)) {
            $input = file_get_contents($this->url_file);
            if ($input) {
                $this->url_stack = array_filter(explode("\n", $input));
                if (empty($this->url_stack)) {
                    echo gmdate("Y-m-d\TH:i:s\Z") . ": \033[31mERROR\033[37m" . ' execute: url_file empty (' . $this->url_file . ')';
                    die;
                }
            }
        } else {
            echo gmdate("Y-m-d\TH:i:s\Z") . ": \033[31mERROR\033[37m" . ' execute: url_file not found! (' . $this->url_file . ')';
            die;
        }
        if ($this->proxy_enabled) {
            if (is_file($this->proxy_file)) {
                $input = file_get_contents($this->url_file);
                if ($input) {
                    $this->proxy_list = array_filter(explode("\n", $input));
                    if (empty($this->proxy_list)) {
                        echo gmdate("Y-m-d\TH:i:s\Z") . ": \033[31mERROR\033[37m" . ' execute: proxy_list empty (' . $this->proxy_list . ')';
                        die;
                    }
                }
            }
        }

        $this->executeCacheRequest();
    }

    /**
     * @todo finish this (fititnt, 2016-01-16 03:20)
     *
     * @return string
     */
    protected function executeAssetRequest()
    {
        return '@todo';
    }

    /**
     * For each URL to request, ask google cache
     *
     */
    protected function executeCacheRequest()
    {
        $reqs_per_hour = '---';

        $total = count($this->url_stack);

        foreach ($this->url_stack AS $url) {
            if ($this->debug_level) {
                file_put_contents(getcwd() . '/' . $this->info_file_processed, $url . PHP_EOL, FILE_APPEND);
            }
            $this->request_count += 1;
            if ($this->request_count > 2) {
                $reqs_per_hour = ($this->request_count / (time() - $this->start_time)) * 60 * 60;
            }

            if ($this->google_cache_use) {
                echo gmdate("Y-m-d\TH:i:s\Z") . ': INFO executeCacheRequest: Request nº ' . $this->request_count . ' of ' . $total
                . ' from Google Cache ' . $reqs_per_hour . ' req/h. Next URL: ' . $url . PHP_EOL;
            } else {
                echo gmdate("Y-m-d\TH:i:s\Z") . ': INFO executeCacheRequest: Request nº ' . $total
                . ' direct from site ' . $reqs_per_hour . ' req/h. Next URL: ' . $url . PHP_EOL;
            }

            $url_to_html_page = $this->google_cache_use ? $this->base_cache . $this->base_site . $url : $this->base_site . $url;

            $result = $this->getUrl($url_to_html_page, $this->getSavePath($url));
            if ($result === false) {
                $this->error_count_now += 1;
                if ($this->error_count_now > $this->error_count_max) {
                    echo gmdate("Y-m-d\TH:i:s\Z") . ": \033[31mCRITICAL\033[37m" . ' executeCacheRequest: Too many errors. Stoping now' . PHP_EOL;
                    die;
                }
                $sleep = $this->wait_error * $this->error_count_now;
                echo gmdate("Y-m-d\TH:i:s\Z") . ': ALERT executeCacheRequest: ERROR 5XX or 3XX! ' . $sleep . 's' . PHP_EOL;
            } else {
                $sleep = rand($this->wait_min, $this->wait_max);
                echo gmdate("Y-m-d\TH:i:s\Z") . ': INFO executeCacheRequest: wait for ' . $sleep . 's' . PHP_EOL;
            }
            sleep($sleep);
        }
        echo gmdate("Y-m-d\TH:i:s\Z") . ': INFO executeCacheRequest: Google Cache Site Recover finished' . PHP_EOL;
        die;
    }

    /**
     * Return generic variable
     * 
     * @var        string          $name: name of var to return
     *
     * return       mixed          $this->$name: value of var
     */
    public function get($name)
    {
        return isset($this->$name) ? $this->$name : null;
    }

    /**
     * @deprecated
     * @param type $html_string
     * @return array
     */
    public function getHtmlResources($html_string)
    {
        $assets = [
            'js' => [],
            'css' => [],
            'links' => [],
        ];
        libxml_use_internal_errors(true); // HTML5 ¯\_(ツ)_/¯
        $doc = new DOMDocument();
        if ($doc->loadHTML($html_string)) {
            $dom = simplexml_import_dom($doc);
            $xpath = new DOMXPath($doc);
            $images = $xpath->query("//img");
            $js = $xpath->query("//script");
            $css = $xpath->query("//link");
            //$src = $nodes->item(0)->getAttribute('src');
            var_dump($images, $js, $css);
            //echo $html_string;
        }

        return $assets;
    }

    protected function getProxy()
    {
        $proxy_now = $this->proxy_list[0];
        echo gmdate("Y-m-d\TH:i:s\Z") . ': INFO getProxy: ' . $proxy_now . PHP_EOL;
        //curl_setopt($curl_handler, CURLOPT_PROXY, $proxy_now);
        //return $curl_handler;
        return $proxy_now;
    }

    /**
     * Return full file path to save on disk for a file of the site
     *
     * @param   String   $url_without_base
     * @return  String
     */
    protected function getSavePath($url_without_base)
    {
        //echo gmdate("Y-m-d\TH:i:s\Z") . ': getSavePath ' . $url_without_base . PHP_EOL;
        $isempty = trim($url_without_base, '/');
        if (empty($isempty) || $url_without_base === $this->save_path) {
            echo gmdate("Y-m-d\TH:i:s\Z") . ': INFO getSavePath: Is page index. Force save as index.html ' . PHP_EOL;
            return $this->save_path . '/index.html';
        } else {
            if ($this->force_html_sufix && !(
                strpos($url_without_base, '.html') !== false ||
                strpos($url_without_base, '.htm') !== false ||
                strpos($url_without_base, '.php') !== false
                )) {
                echo gmdate("Y-m-d\TH:i:s\Z") . ': INFO getSavePath FORCE HTML ' . PHP_EOL;
                return $this->save_path . $url_without_base . '.html';
            } else {
                return $this->save_path . $url_without_base;
            }
        }
    }

    /**
     * 
     * @param   String   $url
     * @param   String   $save_on
     * 
     * @returns Boolean|NULL  True for 200 OK, false for 404, null for 5xx errors
     */
    protected function getUrl($url, $save_on)
    {

        $content = $this->getUrlContents($url);
        echo gmdate("Y-m-d\TH:i:s\Z") . ': INFO getUrl: Status ' . $this->status_code . '; URL: ' . $url . '; SAVE_ON: ' . $save_on . PHP_EOL;
        switch ($this->status_code) {
            case 200:
                //case 302:
                if ($this->google_cache_use) {
                    $this->saveHtml($this->clearGoogleCacheHeader($content), $save_on);
                } else {
                    $this->saveHtml($content, $save_on);
                }
                $htmlhelper = new HtmlHelper($content);
                if ($htmlhelper->isValid()) {
                    $htmlhelper->setBaseUrl($this->base_site);
                    //var_dump($htmlhelper->getLinkImages());
                    //var_dump($htmlhelper->getLinkJavascript());
                    //var_dump($htmlhelper->getLinkCSS());
                }

                if ($this->debug_level) {
                    file_put_contents(getcwd() . '/' . $this->info_file_doneok, $url . PHP_EOL, FILE_APPEND);
                }
                return true;
            case 404:
                if ($this->debug_level) {
                    file_put_contents(getcwd() . '/' . $this->info_file_done404, $url . PHP_EOL, FILE_APPEND);
                }
                return null;
            default:
                if ($this->debug_level) {
                    file_put_contents(getcwd() . '/' . $this->info_file_error, $url . PHP_EOL, FILE_APPEND);
                }
                return false;
        }
    }

    /**
     * Return contents of url
     *
     * @var         string      $url
     * @var         string      $certificate path to certificate if is https URL
     * @return      string
     */
    protected function getUrlContents($url, $certificate = FALSE)
    {
        //echo gmdate("Y-m-d\TH:i:s\Z") . ': getUrlContents ' . $url . PHP_EOL;

        $ch = curl_init();

        if ($this->proxy_enabled) {
            $proxy_now = $this->getProxy($ch);
            curl_setopt($ch, CURLOPT_PROXY, $proxy_now);
        }


        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $certificate);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_USERAGENT, $this->user_agent);
        $content = curl_exec($ch);
        $this->status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($this->debug_level) {
            file_put_contents(getcwd() . '/' . $this->info_file_raw, $content);
        }
        if (curl_errno($ch)) {
            echo gmdate("Y-m-d\TH:i:s\Z") . ": \033[31mERROR\033[37m" . ' getUrlContents CURL_ERROR ' . curl_error($ch) . PHP_EOL;
        }

        //print_r(curl_getinfo($ch));

        curl_close($ch);
        return $content;
    }

    /**
     * For a list of files, read then, convert to array, control caracters
     * and then save then on a internal variable
     *
     * @param   String        $param
     * @param   String|Array  $files
     * @returns Array
     */
    public function importParam($param, $files)
    {
        $data = [];
        if (!is_array($files)) {
            if (strpos(',')) {
                $files = array_map('trim', implode(',', $files));
            } else {
                $files = [$files];
            }
        }
        foreach ($files AS $file) {
            $array = [];
            if (is_file($file) && is_readable($file)) {
                $string = file_get_contents($file);
                $array = explode(PHP_EOL, $string);
                $data = array_merge($data, $array);
            }
        }
        $data = array_unique(array_filter($data));
        var_dump(count($data));
        if (count($data)) {
            $this->$param = $data;
        } else {
            echo gmdate("Y-m-d\TH:i:s\Z") . ': WARNING importParam cannot import ' . json_encode($files);
        }
        return isset($this->$param) ? $this->$param : null;
    }

    /**
     * Recursive create all directories need for salve a file
     *
     * @param  String  $file_path
     * @return boolean
     */
    protected function prepareFilePath($file_path)
    {
        $dir = dirname($file_path);
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0755, true)) {
                echo gmdate("Y-m-d\TH:i:s\Z") . ": \033[31mERROR\033[37m" . ' prepareFilePath  cannot create ' . $dir . ' for file ' . $file_path . PHP_EOL;
                return false;
            }
        }
        return true;
    }

    /**
     * 
     * @param   String   $content
     * @param   String   $save_on
     */
    protected function saveHtml($content, $save_on)
    {
        //echo gmdate("Y-m-d\TH:i:s\Z") . ': INFO saveHtml: ' . $save_on . PHP_EOL;
        if ($this->prepareFilePath($save_on)) {
            //echo gmdate("Y-m-d\TH:i:s\Z") . ': INFO saveHtml:  file_path OK :' . $save_on . PHP_EOL;
            if (!file_put_contents($save_on, $content)) {
                echo gmdate("Y-m-d\TH:i:s\Z") . ": \033[31mERROR\033[37m" . ' saveHtml: cannot save :' . $save_on . PHP_EOL;
            }
        }
    }

    /**
     * Set one generic variable the desired value
     * 
     * @var        string          $name: name of var to return
     *
     * return       object          $this
     */
    public function set($name, $value)
    {
        $this->$name = $value;
        return $this;
    }
}

class HtmlHelper
{

    protected $base_url = '';

    /**
     *
     * @var \DOMElement
     */
    protected $a_nodes;

    /**
     *
     * @var \DOMElement
     */
    protected $css_nodes;
    protected $dom;
    protected $html_string;

    /**
     *
     * @var \DOMElement
     */
    protected $js_nodes;
    protected $img_nodes;

    /**
     *
     * @var \DOMElement
     */
    protected $is_valid = false;

    public function __construct($html_string)
    {
        libxml_use_internal_errors(true); // HTML5 ¯\_(ツ)_/¯
        $doc = new DOMDocument();
        if ($doc->loadHTML($html_string)) {
            $dom = simplexml_import_dom($doc);
            $xpath = new DOMXPath($doc);
            $this->img_nodes = $xpath->query("//img");
            $this->js_nodes = $xpath->query("//script");
            $this->css_nodes = $xpath->query("//link[@rel=stylesheet]");
            $this->css_nodes = $xpath->query("//link[@rel='stylesheet']");
            //$this->css_nodes = $xpath->query("//link");
            //var_dump($this->css_nodes); die;
            //$src = $nodes->item(0)->getAttribute('src');
            //var_dump($this->img_nodes, $this->js_nodes, $this->css_nodes);
            //echo $html_string;
            $this->is_valid = true;
        }
    }

    public function isValid()
    {
        return $this->is_valid;
    }

    public function getLinkCSS($loca_only = true)
    {
        $links = [];
        //var_dump(($this->css_nodes->item(0)));
        //var_dump($this->css_nodes[0]);
        //var_dump(count($this->css_nodes));
        //die('oioi');
        foreach ($this->css_nodes AS $node) {
            $href = $node->getAttribute('href');

            if (empty($src) || (strpos($src, '//') !== false && strpos($src, $this->base_url) === false)) {
                // Empty or remote url
                continue;
            }
            //var_dump($node);

            $links[] = $href;
        }
        //var_dump(count($this->css_nodes));
        //var_dump($links);
        return $links;
    }

    public function getLinkImages($loca_only = true)
    {
        $urls = [];
        foreach ($this->img_nodes AS $node) {
            // No inline images
            if (strpos($node->getAttribute('src'), 'base64') === false) {
                
            }
        }
    }

    public function getLinkJavascript($loca_only = true)
    {
        $urls = [];
        foreach ($this->js_nodes AS $node) {
            // No inline images
            $src = $node->getAttribute('src');
            if (empty($src) || (strpos($src, '//') !== false && strpos($src, $this->base_url) === false)) {
                // Empty or remote url
                continue;
            }

            // Remove base URL to normatize output. Maybe this is not necessary. No time to test now
            $src = str_replace($this->base_url, '', $src);

            $urls[] = $src;
        }
        return $urls;
    }

    public function setBaseUrl($url)
    {
        $this->base_url = $url;
        return $this;
    }
}

$gcsr = new GoogleCacheSiteRecover();

if (empty($argv) || count($argv) < 2) {
    echo 'Usage: gcsr.php http://site.com' . PHP_EOL;
    echo '       1º param: site url (no / at the end)' . PHP_EOL;
    echo '       2º param: file with urls (optimal, default to urls.txt)' . PHP_EOL;
    echo '       3º param: Google Cache URL (optimal, you can repeat site url again to direct from site)' . PHP_EOL;
    die;
} else {
    if (!isset($argv[2])) {
        $argv[2] = 'urls.txt';
    }
    if (isset($argv[3])) {
        $gcsr->set('google_cache_use', false);
    }
}
//echo gmdate("Y-m-d\TH:i:s\Z") . ": \033[31mERROR\033[37m" . ' saveHtml: cannot save :' . $save_on . PHP_EOL;
// ./gcsr.php http://www.fititnt.org urls_test.txt
$gcsr->set('google_cache_use', false)->set('wait_min', 3)->set('wait_max', 5);

$gcsr->set('base_site', $argv[1])->set('url_file', $argv[2])->execute();

//$gcsr->importParam('ignore', ['ignore.txt']);
//var_dump($gcsr->get('ignore'));
