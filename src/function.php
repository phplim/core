<?php
declare (strict_types = 1);

spl_autoload_register('loader');

use \lim\Configer;
use \lim\Server;

Configer::init();


/**
 * 创建目录
 * @Author   Wayren
 * @DateTime 2021-12-20T14:15:08+0800
 * @param    string                   $dirs [description]
 * @param    [type]                   $base [description]
 * @return   [type]                         [description]
 */
function lim_mkdir($dirs='',$base='')
{
    $dirs =  explode(',',$dirs);

    foreach ($dirs as $path) {
        
        $dir = $base.$path;
        
        if (is_dir($dir)) {
            echo 'isdir '.$dir.PHP_EOL;
            continue;
        }

        mkdir($dir, 0777);

        echo 'mkdir '.$dir.PHP_EOL;
    }

}

/**
 * 缓存方法
 * @Author   Wayren
 * @DateTime 2021-12-17T17:34:40+0800
 * @param    [type]                   $k [description]
 * @param    [type]                   $v [description]
 * @param    integer                  $t [description]
 * @return   [type]                      [description]
 */
function uc($k, $v = null, $t = 0)
{
    if (!Server::$cache) {
        Server::$cache = new \Yac();
        wlog('初始化Yac');
    }

    if ($v) {
        return Server::$cache->set($k, $v, $t);
    }

    return Server::$cache->get($k);
}

function ti($fn)
{
    echo '过程开始' . PHP_EOL;
    $s = microtime(true);
    $fn();
    echo '过程结束' . PHP_EOL;
    echo '过程耗时:' . (microtime(true) - $s) . '秒' . PHP_EOL;
}

function f($data=null)
{
    return new \lim\DataHandle($data);
}

function data($data)
{
    return new \lim\DataV($data);
}

function config($key = null)
{
    return Configer::get($key);
}

function suc($data = [], $message = 'success', $code = 1)
{
    if (DATA_CRYPT == 1) {
        $data = \lim\App::crypt($data);
    }
    exit(json_encode(['code' => $code, 'message' => $message, 'data' => $data], 256));
}

function err($message = 'success', $code = 0)
{
    exit(json_encode(['code' => $code, 'message' => $message], 256));
}

function pusher($data = [], $fd = null)
{

    if ($fd === true) {
        foreach (Server::$server->connections as $fd) {
            if (Server::$server->isEstablished($fd)) {
                Server::$server->push((int) $fd, json_encode($data, 256));
            }
        }
        wlog('all');
        return;
    }

    if ($fd === null) {
        print_r('null');
    }

}

function loader($class)
{
    $arr  = explode('\\', $class);
    $file = ROOT . implode('/', $arr) . '.php';
    if (is_file($file)) {
        require_once $file;
    } else {
        echo $file;
        exit(json_encode(['code' => 300, 'msg' => $file . " 不存在"], 256));
    }
}

function rule($rule, $data)
{
    return (new \lim\Rule($rule, $data));
}

function loadFn($dir)
{
    // 打开指定目录
    if ($handle = opendir($dir)) {
        while (($file = readdir($handle)) !== false) {
            if (($file == ".") || ($file == "..")) {
                continue;
            }
            $path = $dir . '/' . $file;

            if (is_dir($path)) {
                loadFn($path);
                continue;
            }

            if ($file == 'function.php') {
                require $path;
                // echo($path.'  '.$file.PHP_EOL);
            }

        }
        closedir($handle);
    }
}

//运行对象或对象方法
function objRun($obj = '', ...$opt)
{
    $obj = explode(':', $obj);

    if (!$class = $obj[0] ?? null) {
        exit('obj 空');
    }

    $class = '\\' . str_replace('.', '\\', $class);

    try {
        //判断是否有方法
        if (!$method = $obj[1] ?? null) {
            new $class(...$opt);
            return;
        }

        //判断方法是否存在
        if (!method_exists($class, $method)) {
            exit($class . ' ' . $method . ' 方法不存在');
        }

        //判断静态方法
        if ((new \ReflectionMethod($class, $method))->isStatic()) {
            $class::$method(...$opt);
        } else {
            (new $class)->$method(...$opt);
        }

    } catch (\Swoole\ExitException $e) {
        wlog($e->getStatus());
    }

}

function wlog($v = '', $f = '')
{
    if ($f) {
        $file = ROOT . 'logs/' . date('Y-m-d') . '-' . $f . '.log';
    } else {
        $file = ROOT . 'logs/' . date('Y-m-d') . $f . '.log';
    }

    $v   = is_array($v) ? json_encode($v, 256) : $v;
    $log = date('H:i:s') . '|' . $v . PHP_EOL;
    file_put_contents($file, $log, FILE_APPEND);
    if (PHP_SAPI == 'cli') {
        echo $log;
    }
}

function db($db = 'db')
{
    return (new \lim\Db())->use($db);
}

if (is_file(APP . 'function.php')) {
    require APP . 'function.php';
}
