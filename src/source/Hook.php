<?php
declare (strict_types = 1);
namespace app;


class Hook
{
    public static function boot()
    {
        wlog('app boot');
        $GLOBALS['config']['aa']=time();
    }

    // public static function task()
    // {
    //    // print_r(config());
    // }

    public static function request()
    {
        // code...
    }

    public static function nginx()
    {
        // code...
    }

    public static function websocket()
    {
        // code...
    }
}
