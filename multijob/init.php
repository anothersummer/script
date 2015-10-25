<?php


define('DEBUG',0);
ini_set('memory_limit','2048M');
define('HOME_PATH',  dirname(__FILE__));
if(false !== strpos($_SERVER['PWD'],HOME_PATH.'/script' )){
    define('LOG_PATH', str_replace(HOME_PATH.'/script',HOME_PATH.'/log',$_SERVER['PWD'].'/'));
    define('DATA_PATH',  str_replace(HOME_PATH.'/script',HOME_PATH.'/data',$_SERVER['PWD'].'/'));
    define('CACHE_PATH', str_replace(HOME_PATH.'/script',HOME_PATH.'/cache',$_SERVER['PWD'].'/'));
}else{
    define('LOG_PATH',HOME_PATH.'/log/');
    define('DATA_PATH',HOME_PATH.'/data/');
    define('CACHE_PATH',HOME_PATH.'/cache/');
}
define('LOG_TONGJI',HOME_PATH.'/log/tongji');
define('BIN_PATH',HOME_PATH.'/bin/');
if(!is_dir(LOG_PATH))mkdir(LOG_PATH, 0777, true);
if(!is_dir(DATA_PATH))mkdir(DATA_PATH, 0777, true);
if(!is_dir(CACHE_PATH))mkdir(CACHE_PATH, 0777,true);
chmod(LOG_PATH, 0755);
chmod(DATA_PATH, 0755);
chmod(CACHE_PATH, 0755);
define('CONF_PATH',  HOME_PATH . '/conf/');
define('LIB_PATH',   HOME_PATH . '/lib/');
if (DEBUG) {
    error_reporting(E_ALL ^ E_NOTICE);
    ini_set('display_errors', 'On');
} else {
    error_reporting(0);
    ini_set('display_errors', 'Off');
}
spl_autoload_register( 'loader');

function loader($class) {
    if (class_exists($class, false)) {
        return;
    }
    $class_path = strtolower(str_replace('_', '/', $class));
    $file = HOME_PATH . '/' . $class_path . '.php';
    if (!file_exists($file)) {
        return;
    }
    require_once($file);
    return;
}

$tongji_obj = new tongji();
class tongji
{
    public function __construct()
    {
        $_ENV['tongji'] = array();
        $_ENV['tongji']['script_name'] = $_SERVER['SCRIPT_NAME'];
        $_ENV['tongji']['cost'] = time();
    }
    public function __destruct()
    {
        $_ENV['tongji']['cost'] = time() - $_ENV['tongji']['cost'];
        Lib_Log::tongji($_ENV['tongji']);
    }
}
