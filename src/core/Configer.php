<?php
declare (strict_types = 1);
namespace lim;

use function \Swoole\Coroutine\run;

class Configer
{
    private static $key = [] ,$data = [];

    public static function init()
    {
        self::$data = static::filesToArray('config');
        self::$data['model'] = static::filesToArray('config/data');
        self::$data['rule'] = static::filesToArray('config/rule',fn($name,$path)=>static::pareRule($name, include $path));
        static::loadDb();
        ksort(self::$data);
        Server::$extend = self::$data;
        wlog('配置文件');
    }


    public static function set($value='')
    {
        // todo
    }

    public static function get($key = '')
    {

        if(!Server::$extend){
            static::init();
        }

        $c = Server::$extend;

        if (!$key) {
            return $c;
        }

        $arr= explode('.', $key);

        return self::dotSplit($arr,$c);
    }

    /**
     * 解析点号分割
     * @Author   Wayren
     * @DateTime 2021-12-15T16:25:05+0800
     * @param    [type]                   $arr [description]
     * @param    [type]                   $ret [description]
     * @return   [type]                        [description]
     */
    public static function dotSplit($arr,$ret)
    {
        $k  = array_shift($arr);
      
        if (!isset($ret[$k])) {
            return null;
        }

        if ($arr) {
            return self::dotSplit($arr,$ret[$k]);
        }

        return $ret[$k];
    }

    /**
     * 配置文件转数组
     * @Author   Wayren
     * @DateTime 2021-12-15T15:55:53+0800
     * @param    [type]                   $path [description]
     * @param    [type]                   $fn   [description]
     * @return   [type]                         [description]
     */
    public static function filesToArray($path,$fn=null)
    {
        $config = [];
        $dir    = APP . $path;
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

                //如果存在回调函数就把文件名 和文件路径传给回调函数
                if($fn){
                    $config[$name] = $fn($name,$path);
                } else {
                    $config[$name] = include $path;
                }
            }
            closedir($handle);
        }
        return $config ;
    }

    /**
     * 数据库配置文件
     * @Author   Wayren
     * @DateTime 2021-12-15T15:55:40+0800
     * @return   [type]                   [description]
     */
    public static function loadDb()
    {
        $db = self::$data['db']['mysql']['default'];
        $pdo = Db::pdo($db);
        //加载配置常量
        $ext = $pdo->query("SELECT `key`,`value`,`type` FROM lim_config")->fetchAll();
        foreach ($ext as $k => $v) {

            if (in_array(substr($v['value'], 0, 1), ['{', '['])) {
                $v['value'] = json_decode($v['value'], true);
            }

            if ($v['type'] == 1 && !defined($v['key'])) {
                define($v['key'], $v['value']);
            }

            if ($v['type'] == 2 && !isset(self::$data[$v['key']])) {
                self::$data[$v['key']]=$v['value'];
            }
        }

        if (!defined('DATA_CRYPT')) {
            define('DATA_CRYPT', 0);
        }

        //加载API路由
        $api = $pdo->query("SELECT * FROM lim_api WHERE status =1 AND top>0 ORDER BY top ASC ,mid ASC ")->fetchAll();
        foreach ($api as $k => $v) {
            $route[strtolower($v['url'])] = [
                strtolower($v['url']), 
                $v['class'], 
                $v['method'], 
                $v['rule'], 
                $v['top'] . '.' . $v['mid'], 
                $v['speed']
            ];
        }

        //加载角色
        $roler = $pdo->query("SELECT * FROM lim_role")->fetchAll();
        foreach ($roler as $k => $v) {
            $role[$v['id']] = json_decode($v['auth'], true);
        }
        self::$data['route'] = $route;
        self::$data['role']  = $role;
    }

    private static function pareRule($name, $rules)
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
             
                //提取公共规则
                if (isset($rules['rules'][$var])) {
                    $ruler[$k][$var] = $rules['rules'][$var];
                }

                //提取专用规则
                if (isset($rule[$var])) {
                    $ruler[$k][$var] = $rule[$var];
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