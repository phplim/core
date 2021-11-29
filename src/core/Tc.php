<?
declare (strict_types = 1);
namespace lim;


/**
 * time consuming
 */
class Tc 
{
    public static $key = 0 , $nodeList = [];

    public static function node()
    {
        static::$nodeList[static::$key]['start'] = microtime(true);
    }
}