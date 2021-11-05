<?
declare (strict_types = 1);
namespace lim;


/**
 * make sql 
 */
class ms 
{

    public static function set($col=null,$key='',$value=null)
    {
        return ' '.$col.' = JSON_SET(' . $col . ',\'$."' . $key . '"\',\'' . $value . '\') ';
    }

    public static function in($key=null,$value=[])
    {
        return " {$key} IN ('".implode("','",$value)."') ";
    }
}