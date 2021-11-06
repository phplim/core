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
        $this->loadRule();
 
        if (config('db.mysql')) {
            if (PHP_SAPI == 'cli') {
                run(fn() => $this->loadDb());
            } else {
                $this->loadDb();
            }
        }
        (new \Yac)->set(APP_NAME,$GLOBALS['config']);
    }

    public static function add($key,$value='')
    {
        $GLOBALS['config'][$key]=$value;
        (new \Yac)->set(APP_NAME,$GLOBALS['config']);
    }

    public static function all()
    {
        return $GLOBALS['config'];
    }

    public static function get($key = '')
    {
        $arr       = explode('.', $key);
        self::$key = $GLOBALS['config'];
        self::configParse($arr);
        return self::$key;
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

        if (!defined('DATA_CRYPT')) {
            define('DATA_CRYPT', 0);
        }

        //加载API路由
        $api = Db::table('lim_api')->select(['status' => 1, 'top|>' => 0, 'ORDER' => ['top' => 'ASC', 'mid' => 'ASC']]);
        foreach ($api as $k => $v) {
            $route[strtolower($v['url'])] = [strtolower($v['url']), $v['class'], $v['method'], $v['rule'], $v['top'] . '.' . $v['mid'], $v['speed']];
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
        if (is_dir($dir) && $handle = opendir($dir)) {
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

    public function loadRule()
    {
        $config = [];
        $dir    = APP . 'rule';
        if (is_dir($dir) && $handle = opendir($dir)) {
            while (($file = readdir($handle)) !== false) {
                if (($file == ".") || ($file == "..")) {
                    continue;
                }
                $path = $dir . '/' . $file;

                if (is_dir($path)) {
                    continue;
                }
                $name = strstr($file, '.', true);

                
                $GLOBALS['config']['rules'][$name] = $this->pareRule($name, include $path);
            }
            closedir($handle);
        }
        // print_r($GLOBALS['config']['rules']);
        // // $GLOBALS['config'] = $config;
    }

    private function pareRule($name, $rules)
    {

        foreach ($rules['methods'] as $k => $v) {
            $rule = $v['rule'] ?? []; //提取专有规则

            //必选规则
            if (isset($v['must'])) {
                $must = explode(',',$v['must']);
            } else {
                $must = [];
            }
            
            if (isset($v['vars'])) {
                $vars = $v['vars']=='*'? array_keys($rules['rules']) :explode(',',$v['vars']);
            } else {
                $vars = [];
            }
            
            $vars = array_unique(array_merge($vars, $must, array_keys($rule))); //合法变量
           
            foreach ($vars as $var) {

                //提取专用规则
                if (isset($rule[$var])) {
                    $ruler[$k][$var] = $rule[$var];
                }
             
                //提取公共规则
                if (isset($rules['rules'][$var])) {
                    $ruler[$k][$var] = $rules['rules'][$var];
                }

                //组合必选规则
                if (in_array($var, $must)) {
                    if (!isset($ruler[$k][$var])) {
                        wlog($name.' '.$var);
                        exit;
                    }
                    $ruler[$k][$var] = str_replace('@', '@must|', $ruler[$k][$var] );
                }
            }
        }

        return $ruler;
    }

}
