#!/usr/bin/php

<?php

class GCacheCrawler
{

    /**
     * Base. Use if your list just have site path, not full url
     *
     * @var String 
     */
    protected $base_cache = '';
    protected $base_site = '';
    protected $debug_level = 1;
    protected $save_path = '';
    protected $info_file_processed = 'gcachecrawler_processed.txt';
    protected $info_file_lasttry = 'gcachecrawler_lastitem.txt';
    protected $info_file_doneok = 'gcachecrawler_doneok.txt';
    protected $info_file_done404 = 'gcachecrawler_done404.txt';
    protected $info_file_error = 'gcachecrawler_error.txt';
    protected $info_file_raw = 'gcachecrawler_raw.html';
    protected $sufix = '&hl=pt-BR&ct=clnk&gl=br&client=ubuntu';
    protected $user_agent = 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Ubuntu Chromium/47.0.2526.73 Chrome/47.0.2526.73 Safari/537.36';
    protected $status_code = null;
    protected $url_file = null;
    protected $url_stack = [];
    protected $wait_error = 300;
    protected $wait_myhost = 1;
    protected $wait_min = 10;
    protected $wait_max = 30;
    protected $error_max_count = 5;

    /**
     * Initialize values
     */
    function __construct()
    {
        $this->save_path = getcwd() . '/output';
    }

    /**
     * Return contents of url
     * @var         string      $url
     * @var         string      $certificate path to certificate if is https URL
     * @return      string
     */
    protected function getUrlContents($url, $certificate = FALSE)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $certificate);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_USERAGENT, $this->user_agent);
        $content = curl_exec($ch);
        $this->status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($this->debug_level) {
            file_put_contents(getcwd() . '/' . $this->info_file_raw, $content);
        }

        curl_close($ch); //Feche 
        return $content;
    }

    /**
     * 
     * @param   String   $url
     * @param   String   $save_on
     */
    protected function getUrl($url, $save_on)
    {

        $content = $this->getUrlContents($url);
        echo 'getUrl: STATUS ' . $this->status_code . '; URL:' . $url . '; SAVE_ON ' . $save_on . PHP_EOL;
        switch ($this->status_code) {
            case 200:
                $this->saveUrl($content, $save_on);
                if ($this->debug_level) {
                    file_put_contents(getcwd() . '/' . $this->info_file_doneok, $url . PHP_EOL, FILE_APPEND);
                }
                break;
            case 404:
                if ($this->debug_level) {
                    file_put_contents(getcwd() . '/' . $this->info_file_done404, $url . PHP_EOL, FILE_APPEND);
                }
                break;
            default:
                if ($this->debug_level) {
                    file_put_contents(getcwd() . '/' . $this->info_file_error, $url . PHP_EOL, FILE_APPEND);
                }
                break;
        }
    }

    /**
     * 
     * @param   String   $url_without_base
     * @return  String
     */
    protected function getSavePath($url_without_base)
    {
        if (empty(trim('/', $url_without_base))) {
            return $this->save_path . '/index.html';
        } else {
            return $this->save_path . $url_without_base;
        }
    }

    /**
     * 
     * @param   String   $content
     * @param   String   $save_on
     */
    protected function saveUrl($content, $save_on)
    {
        echo 'saveUrl :' . $save_on . PHP_EOL;
    }

    protected function prepareDirectory($dir, $from_base = false)
    {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }

    /**
     * Execute
     */
    public function execute()
    {
        print_r($this);

        echo gmdate("Y-m-d\TH:i:s\Z") . ': started' . PHP_EOL;

        if (is_file($this->url_file)) {
            $input = file_get_contents($this->url_file);
            if ($input) {
                $this->url_stack = array_filter(explode("\n", $input));
            }
        } else {
            echo 'ERROR: url_file not found! (' . $this->url_file . ')';
            die;
        }

        if ($this->url_stack) {
            foreach ($this->url_stack AS $url) {
                if ($this->debug_level) {
                    file_put_contents(getcwd() . '/' . $this->info_file_processed, $url . PHP_EOL, FILE_APPEND);
                }
                $this->getUrl($this->base_cache . $this->base_site . $url, $this->getSavePath($url));
                $sleep = rand($this->wait_min, $this->wait_max);
                echo gmdate("Y-m-d\TH:i:s\Z") . ': wait for ' . $sleep . 's' . PHP_EOL;
                sleep($sleep);
            }
        }
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

if (empty($argv) || count($argv) < 2) {
    echo 'Usage: gcachecrawler.php http://site.com' . PHP_EOL;
    echo '       1º param: site url (no / at the end)' . PHP_EOL;
    echo '       2º param: file with urls (optimal, default to urls.txt' . PHP_EOL;
    die;
} else {
    if (!isset($argv[2])) {
        $argv[2] = 'urls.txt';
    }
}
$gcc = new GCacheCrawler($argv);
$gcc->set('base_site', $argv[1])->set('url_file', $argv[2])->execute();

