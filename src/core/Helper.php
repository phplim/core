<?php
declare (strict_types = 1);
namespace lim;

/**
 *助手类
 */
class Helper
{
    public function __construct($app=null)
    {

    }

    public function cache()
    {
        // code...
    }

    public function request()
    {
        // code...
    }

    public static function parseRequest($uri=null)
    {
        $uri = explode('?', strtolower($uri))[0];

        if (str_starts_with($uri, '/configer') && APP_ENV == 'dev') {

            // $res = [$uri, '\\configer\\Configer', explode('/', $uri)[2] ?? 'index', 0, 0, ''];

            $res =['class'=>'\\configer\\Configer','method'=>explode('/', $uri)[2] ?? 'index'];
            return $res;
        }

        $route = config('route');

        if (isset($route[$uri])) {
            return $route[$uri];
        }
        // print_r([$route, $uri]);
        err('非法请求');
    }

}
