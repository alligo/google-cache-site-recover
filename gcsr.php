#!/usr/bin/php

<?php
/*
 * Google Cache Site Recover is a way to recover your site from Google Cache if you are out of luck and have no backups.
 * 
 * Requires PHP5.4+ and php-curl. No more dependencies. Just add this file, chmod +x and execute with params
 * 
 * Author: Emerson Rocha Luiz <emerson at alligo.com>
 */

class GoogleCacheSiteRecover
{

    private $_lastcontent = null;

    /**
     * Base. Use if your list just have site path, not full url
     *
     * @var String
     */
    protected $base_cache = 'http://webcache.googleusercontent.com/search?q=cache:';
    protected $base_site = '';
    protected $debug_level = 1;

    /**
     *
     * @var  \CurlProxyHelper
     */
    protected $cph;

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
    protected $enable_assets_request = true;

    /**
     * Fake user agent. Default curl agent will get you banned
     * 
     * @deprecated
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
        $this->cph = new CurlProxyHelper;
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
            echo gmdate("Y-m-d\TH:i:s\Z") . ": \033[31mERROR\033[37m" . ' clearGoogleCacheHeader: method is outdated. Please update me!' . PHP_EOL;
            return $string;
        } else {
            $parts = explode('style="position:relative;">', $string);
            $good_parts = array_splice($parts, 1);
            $html_string = implode('', $good_parts);

            // This part needs more testing, Force UTF8 encoding for google cache pages
            if (strpos('charset=utf-8', $html_string) === false && strpos('charset=UTF-8', $html_string) === false) {
                $html_string = str_replace('<head>', '<head><meta http-equiv="Content-Type" content="text/html; charset=UTF-8">', $html_string);
            }

            echo gmdate("Y-m-d\TH:i:s\Z") . ' DEBUG clearGoogleCacheHeader: cleared' . PHP_EOL;
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

        $this->executePageRequest();
    }

    /**
     * 
     *
     * @return string
     */
    protected function executePageAssets()
    {

        $htmlhelper = new HtmlHelper($this->_lastcontent);
        if ($htmlhelper->isValid()) {
            $htmlhelper->setBaseUrl($this->base_site);
            $assets = array_filter(array_merge($htmlhelper->getLinkImages(), $htmlhelper->getLinkJavascript(), $htmlhelper->getLinkCSS()));
            echo gmdate("Y-m-d\TH:i:s\Z") . ' INFO executePageAssets: possible local Assets found ' . count($assets) . PHP_EOL;
            //var_dump($assets);
            foreach ($assets AS $asset) {
                if (!$this->isUrlIgnore($asset)) {
                    $url = $this->base_site . $asset;
                    $save_on = $this->save_path . $asset;
                    $this->ignore[] = $asset;
                    //var_dump($url, $save_on);
                    $content = $this->cph->getUrlContents($url);
                    switch ($this->cph->status_code) {

                        case 200:
                            $this->saveFile($content, $save_on);
                            echo gmdate("Y-m-d\TH:i:s\Z") . ': INFO executePageAssets: 200 OK ' . $url . ', Sleep ' . $this->wait_myhost . PHP_EOL;
                            if ($this->debug_level) {
                                file_put_contents(getcwd() . '/gcsr_asset_ok.txt', $url . PHP_EOL, FILE_APPEND);
                            }
                            break;
                        case 404:
                            echo gmdate("Y-m-d\TH:i:s\Z") . ': INFO executePageAssets: 404 not found ' . $url . ', Sleep ' . $this->wait_myhost . PHP_EOL;
                            if ($this->debug_level) {
                                file_put_contents(getcwd() . '/gcsr_asset_404.txt', $url . PHP_EOL, FILE_APPEND);
                            }
                            break;
                        default:
                            echo gmdate("Y-m-d\TH:i:s\Z") . ': INFO executePageAssets: error ' . $this->cph->status_code . ' ' . $url . ', Sleep ' . $this->wait_myhost . PHP_EOL;
                            if ($this->debug_level) {
                                file_put_contents(getcwd() . '/gcsr_asset_error.txt', $url . PHP_EOL, FILE_APPEND);
                            }
                            break;
                    }
                    file_put_contents(getcwd() . '/gcsr_asset_ignored.txt', $asset . PHP_EOL, FILE_APPEND);
                    if ($this->wait_myhost) {
                        sleep($this->wait_myhost);
                    }
                }
            }
        } else {
            echo gmdate("Y-m-d\TH:i:s\Z") . ': ALERT executePageAssets: not a valid HTML ' . PHP_EOL;
        }
    }

    /**
     * Request page request (from Google Cache or, if specified, site itself)
     *
     */
    protected function executePageRequest()
    {
        $reqs_per_hour = '---';

        $total = count($this->url_stack);

        foreach ($this->url_stack AS $url) {

            if ($this->isUrlIgnore($url)) {
                echo gmdate("Y-m-d\TH:i:s\Z") . ': INFO executeCacheRequest: ignoring ' . $url . PHP_EOL;
            }

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
                echo gmdate("Y-m-d\TH:i:s\Z") . ': INFO executeCacheRequest: Request nº ' . $this->request_count . ' of ' . $total
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

                if ($this->enable_assets_request) {
                    $this->executePageAssets();
                }


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
     * Get page HTML
     *
     * @param   String   $url
     * @param   String   $save_on
     * 
     * @returns Boolean|NULL  True for 200 OK, false for 404, null for 5xx errors
     */
    protected function getUrl($url, $save_on)
    {

        $content = $this->getUrlContents($url);
        $this->_lastcontent = $content;
        echo gmdate("Y-m-d\TH:i:s\Z") . ': INFO getUrl: Status ' . $this->status_code . '; URL: ' . $url . '; SAVE_ON: ' . $save_on . PHP_EOL;
        switch ($this->status_code) {
            case 200:
                //case 302:
                if ($this->google_cache_use) {
                    $this->saveFile($this->clearGoogleCacheHeader($content), $save_on);
                } else {
                    $this->saveFile($content, $save_on);
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
     * @deprecated
     *
     * @var         string      $url
     * @var         string      $certificate path to certificate if is https URL
     * @return      string
     */
    protected function getUrlContents($url, $certificate = FALSE)
    {
        $content = $this->cph->getUrlContents($url);
        $this->status_code = $this->cph->status_code;
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
        //var_dump(count($data));
        if (count($data)) {
            $this->$param = $data;
        } else {
            echo gmdate("Y-m-d\TH:i:s\Z") . ': WARNING importParam cannot import ' . json_encode($files);
        }
        return isset($this->$param) ? $this->$param : null;
    }

    /**
     * Return if the ignore or file should be ignored to request again from server
     *
     * @param  String   $file_or_url
     * @return boolean
     */
    protected function isUrlIgnore($file_or_url)
    {
        if ($this->ignore && count($this->ignore)) {
            if (in_array($file_or_url, $this->ignore)) {
                return true;
            }
            if (in_array(str_replace($this->base_site, '', $file_or_url), $this->ignore)) {
                return true;
            }
        }
        return false;
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
     * Save file do disk
     *
     * @param   String   $content
     * @param   String   $save_on
     * 
     * @returns Boolean
     */
    protected function saveFile($content, $save_on)
    {
        //echo gmdate("Y-m-d\TH:i:s\Z") . ': INFO saveFile: ' . $save_on . PHP_EOL;
        if ($this->prepareFilePath($save_on)) {
            //echo gmdate("Y-m-d\TH:i:s\Z") . ': INFO saveFile:  file_path OK :' . $save_on . PHP_EOL;
            if (!file_put_contents($save_on, $content)) {
                echo gmdate("Y-m-d\TH:i:s\Z") . ": \033[31mERROR\033[37m" . ' saveFile: cannot save :' . $save_on . PHP_EOL;
                return false;
            }
            return true;
        }
        return false;
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

class CurlProxyHelper
{

    protected $proxy_enabled = false;
    protected $proxy_list = [];
    protected $debug_level = 1;
    protected $check_ssl = false;
    protected $url = '';
    public $status_code = -1;

    /**
     * Fake user agent. Default curl agent will get you banned
     *
     * @var String
     */
    protected $user_agent = 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Ubuntu Chromium/47.0.2526.73 Chrome/47.0.2526.73 Safari/537.36';

    public function __construct()
    {
        ;
    }

    /**
     * Return contents of url
     *
     * @var         string      $url
     * @var         string      $certificate path to certificate if is https URL
     * @return      string
     */
    public function getUrlContents($url = null)
    {
        if ($url === null) {
            $url = $this->url;
        } else {
            $this->url = $url;
        }
        //echo gmdate("Y-m-d\TH:i:s\Z") . ': getUrlContents ' . $url . PHP_EOL;

        $ch = curl_init();

        if ($this->proxy_enabled) {
            $proxy_now = $this->getProxy($ch);
            curl_setopt($ch, CURLOPT_PROXY, $proxy_now);
        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $this->check_ssl);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_USERAGENT, $this->user_agent);
        $content = curl_exec($ch);
        $this->status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($this->debug_level) {
            file_put_contents(getcwd() . '/' . 'curlproxyhelperdebug.txt', $content);
        }
        if (curl_errno($ch)) {
            echo gmdate("Y-m-d\TH:i:s\Z") . ": \033[31mERROR\033[37m" . ' getUrlContents CURL_ERROR ' . curl_error($ch) . PHP_EOL;
        }

        //var_dump($content); die;
        //print_r(curl_getinfo($ch));

        curl_close($ch);
        return $content;
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
            $this->css_nodes = $xpath->query("//link[@rel='stylesheet']");

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

        foreach ($this->css_nodes AS $node) {
            $href = $node->getAttribute('href');

            if (empty($src) || (strpos($src, '//') !== false && strpos($src, $this->base_url) === false)) {
                // Empty or remote url
                continue;
            }
            $src = str_replace($this->base_url, '', $src);
            //var_dump('>>>', $src, $this->base_url);
            //var_dump($node);

            $links[] = $src;
        }
        return $links;
    }

    public function getLinkImages($loca_only = true)
    {
        $urls = [];
        foreach ($this->img_nodes AS $node) {
            $src = $node->getAttribute('src');
            // No inline images
            if (empty($src) || strpos($src, 'base64') !== false || (strpos($src, '//') !== false && strpos($src, $this->base_url) === false)) {
                // @todo terminar
                continue;
            }
            $src = str_replace($this->base_url, '', $src);
            $urls[] = $src;
        }
        return $urls;
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
//echo gmdate("Y-m-d\TH:i:s\Z") . ": \033[31mERROR\033[37m" . ' saveFile: cannot save :' . $save_on . PHP_EOL;
// ./gcsr.php http://www.fititnt.org urls_test.txt
//$gcsr->set('google_cache_use', false)->set('wait_min', 3)->set('wait_max', 5);
//$gcsr->set('wait_min', 3)->set('wait_max', 5);
$gcsr->set('base_site', $argv[1])->set('url_file', $argv[2])->execute();

//$gcsr->importParam('ignore', ['ignore.txt']);
//var_dump($gcsr->get('ignore'));
