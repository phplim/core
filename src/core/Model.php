<?
declare (strict_types = 1);
namespace lim;

class Model
{
    public static $user = false, $database = APP_ENV, $table = null, $rule = null, $data = [];

    // public static function __callStatic($name, $arguments)
    // {
    //     call_user_func_array(array(__CLASS__, $name), $arguments);
    // }

    public static function __callStatic($method, $args)
    {
        $class = '\\app\\data\\'.$method;

        print_r([$method,$args,$class]);
        try {
            return call_user_func_array([new $class(), $method], $args);
        } catch (Throwable $e) {
            print_r($e);
        }
    }
}
