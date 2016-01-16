#!/usr/bin/php

<?php

class GCacheCrawler
{

    /**
     * Base. Use if your list just have site path, not full url
     *
     * @var String 
     */
    protected $base = '';
    protected $debug_level = 1;
    protected $info_file_lasttry = 'gcachecrawler_lastitem.txt';
    protected $info_file_doneok = 'gcachecrawler_doneok.txt';
    protected $info_file_done404 = 'gcachecrawler_done404.txt';
    protected $sufix = '&hl=pt-BR&ct=clnk&gl=br&client=ubuntu';

    /*
     * Initialize values
     */

    function __construct()
    {
        //
    }
    /*
     * Return contents of url
     * @var         string      $url
     * @var         string      $certificate path to certificate if is https URL
     * @return      string
     */

    protected function _getUrlContents($url, $certificate = FALSE)
    {
        $ch = curl_init(); //Inicializar a sessao           
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); //Retorne os dados em vez de imprimir em tela
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $certificate); //Check certificate if is SSL, default FALSE
        curl_setopt($ch, CURLOPT_URL, $url); //Setar URL
        $content = curl_exec($ch); //Execute
        curl_close($ch); //Feche 
        return $content;
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
