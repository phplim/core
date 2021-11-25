<?
declare (strict_types = 1);
namespace lim;


/**
 * time consuming
 */
class Tc 
{
    public static $begin = null , $nodeList = [];


    public static function in($key=null,$value=[])
    {
        return " {$key} IN ('".implode("','",$value)."') ";
    }
}