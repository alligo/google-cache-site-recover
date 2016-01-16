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
    protected $info_file_lasttry = 'gcachecrawler_lastitem.txt';
    protected $info_file_doneok = 'gcachecrawler_doneok.txt';
    protected $info_file_done404 = 'gcachecrawler_done404.txt';
    protected $info_file_raw = 'gcachecrawler_raw.html';
    protected $sufix = '&hl=pt-BR&ct=clnk&gl=br&client=ubuntu';
    protected $status_code = null;
    protected $url_stack = [];

    /*
     * Initialize values
     */

    function __construct($argv = null)
    {
        $this->save_path = getcwd() . '/output';

        if (is_file('urls.txt')) {
            $input = file_get_contents('urls.txt');
            if ($input) {
                $this->url_stack = array_filter(explode("\n", $input));
            }
        }
    }
    /*
     * Return contents of url
     * @var         string      $url
     * @var         string      $certificate path to certificate if is https URL
     * @return      string
     */

    protected function getUrlContents($url, $certificate = FALSE)
    {
        $ch = curl_init(); //Inicializar a sessao           
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); //Retorne os dados em vez de imprimir em tela
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $certificate); //Check certificate if is SSL, default FALSE
        curl_setopt($ch, CURLOPT_URL, $url); //Setar URL
        $content = curl_exec($ch); //Execute
        $this->status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch); //Feche 
        return $content;
    }

    protected function getUrl($url, $save_on)
    {

        $content = $this->getUrlContents($url);
        echo 'getUrl: STATUS ' . $this->status_code . '; URL:' . $url . '; SAVE_ON ' . $save_on . PHP_EOL;
        if ($this->status_code === 200) {
            $this->saveUrl($content, $save_on);
        }
    }

    protected function getSavePath($url_without_base)
    {
        if (empty(trim('/', $url_without_base))) {
            return $this->save_path . '/index.html';
        } else {
            return $this->save_path . $url_without_base;
        }
    }

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

    public function execute()
    {
        print_r($this);
        if ($this->url_stack) {
            foreach ($this->url_stack AS $url) {
                $this->getUrl($this->base_cache . $this->base_site . $url, $this->getSavePath($url));
                sleep(3);
            }
        }
    }
    /*
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
    /*
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

$gcc = new GCacheCrawler($argv);
$gcc->set('base_site', 'http://www.fititnt.org')->execute();
