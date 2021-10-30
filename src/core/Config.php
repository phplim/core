<?php
declare (strict_types = 1);
namespace lim;

use function \Swoole\Coroutine\run;

class Config
{
    private static $key = [];

    public function __construct()
    {
        define('APP_ENV', is_file(ROOT . '.dev') ? 'dev' : 'pro');

        $this->loadFile();

        if (config('db.mysql')) {
            if (PHP_SAPI == 'cli') {
                run(fn() => $this->loadDb());
            } else {
                $this->loadDb();
            }
        }
    }

    public static function all()
    {
        return $GLOBALS['config'];
        // print_r(get_defined_constants());
    }

    public static function get($key = '')
    {
        $arr       = explode('.', $key);
        self::$key = $GLOBALS['config'];
        self::configParse($arr);
        return self::$key;
        // print_r(self::$key);
    }

    public static function configParse($arr)
    {
        $k         = array_shift($arr);
        self::$key = self::$key[$k] ?? null;

        if (self::$key == null) {
            return null;
        }

        if ($arr) {
            self::configParse($arr);
        }
    }

    public function loadDb($value = '')
    {
        //加载配置常量
        $ext = \lim\Db::table('lim_config')->cols('key,value,type')->select();
        foreach ($ext as $k => $v) {

            if (in_array(substr($v['value'], 0, 1), ['{', '['])) {
                $v['value'] = json_decode($v['value'], true);
            }

            if ($v['type'] == 1 && !defined($v['key'])) {
                define($v['key'], $v['value']);
            }
        }

        //加载API路由
        $api = Db::table('lim_api')->select(['status' => 1, 'top|>' => 0, 'ORDER' => ['top' => 'ASC', 'mid' => 'ASC']]);
        foreach ($api as $k => $v) {
            $route[$v['url']] = [$v['url'], $v['class'], $v['method'], $v['top'] . '.' . $v['mid'], $v['speed']];
        }

        //加载角色
        $roler = Db::table('lim_role')->select();
        foreach ($roler as $k => $v) {
            $role[$v['id']] = json_decode($v['auth'], true);
        }
        $GLOBALS['config']['route'] = $route;
        $GLOBALS['config']['role']  = $role;
    }

    public function loadFile()
    {
        $config = [];
        $dir    = APP . 'config';
        if ($handle = opendir($dir)) {
            while (($file = readdir($handle)) !== false) {
                if (($file == ".") || ($file == "..")) {
                    continue;
                }
                $path = $dir . '/' . $file;

                if (is_dir($path)) {
                    continue;
                }
                $name          = strstr($file, '.', true);
                $config[$name] = include $path;
            }
            closedir($handle);
        }
        $GLOBALS['config'] = $config;
    }

}
