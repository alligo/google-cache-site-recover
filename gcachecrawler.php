#!/usr/bin/php

<?php

class GCacheCrawler
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
    protected $info_file_processed = 'gcachecrawler_processed.txt';
    protected $info_file_lasttry = 'gcachecrawler_lastitem.txt';
    protected $info_file_doneok = 'gcachecrawler_doneok.txt';
    protected $info_file_done404 = 'gcachecrawler_done404.txt';
    protected $info_file_error = 'gcachecrawler_error.txt';
    protected $info_file_raw = 'gcachecrawler_raw.html';

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
    protected $wait_min = 10;

    /**
     * Maximum time, in seconds, to take page from google cache
     *
     * @var Integer
     */
    protected $wait_max = 30;
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
            echo gmdate("Y-m-d\TH:i:s\Z") . 'ERROR: clearGoogleCacheHeader method is outdated. Please update me!';
            return $string;
        } else {
            $parts = explode('style="position:relative;">', $string);
            $good_parts = array_splice($parts, 1);
            echo gmdate("Y-m-d\TH:i:s\Z") . ' OK clearGoogleCacheHeader';
            return implode('', $good_parts);
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
                    echo gmdate("Y-m-d\TH:i:s\Z") . 'ERROR: url_file empty (' . $this->url_file . ')';
                    die;
                }
            }
        } else {
            echo gmdate("Y-m-d\TH:i:s\Z") . 'ERROR: url_file not found! (' . $this->url_file . ')';
            die;
        }
        if ($this->proxy_enabled) {
            if (is_file($this->proxy_file)) {
                $input = file_get_contents($this->url_file);
                if ($input) {
                    $this->proxy_list = array_filter(explode("\n", $input));
                    if (empty($this->proxy_list)) {
                        echo gmdate("Y-m-d\TH:i:s\Z") . 'ERROR: proxy_list empty (' . $this->proxy_list . ')';
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

        foreach ($this->url_stack AS $url) {
            if ($this->debug_level) {
                file_put_contents(getcwd() . '/' . $this->info_file_processed, $url . PHP_EOL, FILE_APPEND);
            }
            $this->request_count += 1;
            if ($this->request_count > 2) {
                $reqs_per_hour = ((time() - $this->start_time) * $this->request_count) / 60;
            }

            if ($this->google_cache_use) {
                echo gmdate("Y-m-d\TH:i:s\Z") . ': REQUEST n ' . $this->request_count . ' from Google Cache ' . $reqs_per_hour . ' req/h. Next URL: ' . $url . PHP_EOL;
            } else {
                echo gmdate("Y-m-d\TH:i:s\Z") . ': REQUEST n ' . $this->request_count . ' direct from site ' . $reqs_per_hour . ' req/h. Next URL: ' . $url . PHP_EOL;
            }

            $url_to_html_page = $this->google_cache_use ? $this->base_cache . $this->base_site . $url : $this->base_site . $url;

            $result = $this->getUrl($url_to_html_page, $this->getSavePath($url));
            if ($result === false) {
                $this->error_count_now += 1;
                if ($this->error_count_now > $this->error_count_max) {
                    echo gmdate("Y-m-d\TH:i:s\Z") . ': Too many errors. Stoping now' . PHP_EOL;
                    die;
                }
                $sleep = $this->wait_error * $this->error_count_now;
                echo gmdate("Y-m-d\TH:i:s\Z") . ': ERROR 5XX! ' . $sleep . 's' . PHP_EOL;
            } else {
                $sleep = rand($this->wait_min, $this->wait_max);
                echo gmdate("Y-m-d\TH:i:s\Z") . ': wait for ' . $sleep . 's' . PHP_EOL;
            }
            sleep($sleep);
        }
        echo gmdate("Y-m-d\TH:i:s\Z") . ': Google Cache Site Recover finished' . PHP_EOL;
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
        return $this->$name;
    }

    /**
     * 
     * @param   String   $url_without_base
     * @return  String
     */
    protected function getSavePath($url_without_base)
    {
        echo gmdate("Y-m-d\TH:i:s\Z") . ': getSavePath ' . $url_without_base . PHP_EOL;
		$isempty = trim($url_without_base, '/');
        if (empty($isempty) || $url_without_base === $this->save_path) {
            echo gmdate("Y-m-d\TH:i:s\Z") . ': getSavePath IS INDEX PAGE ' . PHP_EOL;
            return $this->save_path . '/index.html';
        } else {
            if ($this->force_html_sufix && !(
                strpos($url_without_base, '.html') !== false ||
                strpos($url_without_base, '.htm') !== false ||
                strpos($url_without_base, '.php') !== false
                )) {
                echo gmdate("Y-m-d\TH:i:s\Z") . ': getSavePath FORCE HTML ' . PHP_EOL;
                return $this->save_path . $url_without_base . '.html';
            } else {
                return $this->save_path . $url_without_base;
            }
        }
    }

    protected function getProxy()
    {
        $proxy_now = $this->proxy_list[0];
        echo gmdate("Y-m-d\TH:i:s\Z") . ': getProxy ' . $proxy_now . PHP_EOL;
        //curl_setopt($curl_handler, CURLOPT_PROXY, $proxy_now);
        
        //return $curl_handler;
        return $proxy_now;
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
        echo gmdate("Y-m-d\TH:i:s\Z") . ': URL GET, STATUS ' . $this->status_code . '; URL: ' . $url . '; SAVE_ON: ' . $save_on . PHP_EOL;
        switch ($this->status_code) {
            case 200:
                //case 302:
                if ($this->google_cache_use) {
                    $this->saveHtml($this->clearGoogleCacheHeader($content), $save_on);
                } else {
                    $this->saveHtml($content, $save_on);
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
        echo gmdate("Y-m-d\TH:i:s\Z") . ': getUrlContents ' . $url . PHP_EOL;

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
            echo gmdate("Y-m-d\TH:i:s\Z") . ': ERROR getUrlContents CURL_ERROR ' . curl_error($ch) . PHP_EOL;
        }
        
        //print_r(curl_getinfo($ch));

        curl_close($ch);
        return $content;
    }

    /**
     * 
     * @param   String   $content
     * @param   String   $save_on
     */
    protected function saveHtml($content, $save_on)
    {
        echo gmdate("Y-m-d\TH:i:s\Z") . ' saveHtml :' . $save_on . PHP_EOL;
        if ($this->prepareFilePath($save_on)) {
            echo gmdate("Y-m-d\TH:i:s\Z") . ' saveHtml file_path OK :' . $save_on . PHP_EOL;
            if (!file_put_contents($save_on, $content)) {
                echo gmdate("Y-m-d\TH:i:s\Z") . ' ERROR! saveHtml cannot save :' . $save_on . PHP_EOL;
            }
        }
    }

    protected function prepareFilePath($file_path)
    {
        $dir = dirname($file_path);
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0755, true)) {
                echo gmdate("Y-m-d\TH:i:s\Z") . ' ERROR! prepareFilePath  cannot create ' . $dir . ' for file ' . $file_path . PHP_EOL;
                return false;
            }
        }
        return true;
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

$gcsr = new GCacheCrawler();

if (empty($argv) || count($argv) < 2) {
    echo 'Usage: gcachecrawler.php http://site.com' . PHP_EOL;
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

$gcsr->set('google_cache_use', false);

$gcsr->set('base_site', $argv[1])->set('url_file', $argv[2])->execute();
