<?
declare (strict_types = 1);
namespace lim;

class Model
{
    public static $user = false, $database = 'default', $table = null, $rule = null, $data = [], $config = [];

    public function __construct($data = [])
    {

        if ($data) {
            $this->data = $data;
        }
    }

    /**
     * 获取用户请求数据
     * @Author   Wayren
     * @DateTime 2022-01-26T12:38:55+0800
     * @param    [type]                   $res [description]
     * @param    boolean                  $opt [description]
     * @return   [type]                        [description]
     */
    public static function _getData($res, $opt = false)
    {

        if ($opt) {

            $rule = config('rule');
            $arr  = explode('.', strtolower((string) $res->rule));

            foreach ($arr as $k => $v) {
                if (!isset($rule[$v])) {
                    return null;
                }
                $rule = $rule[$v];
            }

            $vars = array_keys($rule);

            //过滤非法参数
            foreach ($res->all as $k => $v) {

                //排除表达式过滤
                if (preg_match('/\|/', $k)) {
                    continue;
                }

                if (!in_array($k, $vars)) {
                    unset($res->all[$k]);
                }
            }
            rule($rule, $res->all)->break();
        }
        return $res->all ?? null;
    }

    public function __call($method, $args)
    {
        // print_r([$method, $args]);
    }

    public static function __callStatic($method, $args)
    {

        try {

            // print_r([$method,$args]);
            // return;
            // static::$config = config($method) ?? [];
            $class = '\\app\\model\\' . $method;

            return new $class(array_shift($args));
        } catch (Throwable $e) {
            print_r($e);
        }
    }

    public static function table($table = '')
    {
        static::$table = $table;
        return static::class;
    }

    public static function check($method, &$data)
    {

        if ($rule = $GLOBALS['config']['rules'][strtolower((string) static::$rule)][$method] ?? null) {
            $vars = array_keys($rule);
            //过滤非法参数
            foreach ($data as $k => $v) {

                //排除表达式过滤
                if (preg_match('/\|/', $k)) {
                    continue;
                }

                if (!in_array($k, $vars)) {
                    unset($data[$k]);
                }
            }
            rule($rule, $data)->break();
        }
        return $data;
    }

    public static function _insert($data=null)
    {
        return Dbs::use (static::$database)->table(static::$table)->insert($data??app('data',true));
    }

    public static function _update($data, $where, $msg = '')
    {
        static::check('update', $data);
        $res = Db::use (static::$database)->table(static::$table)->update($data, $where);
        if ($msg) {
            $res !== null ? suc([], $msg . '成功') : err($msg . '失败');
        }
        return $res;
    }

    public static function _delete($data, $msg = '')
    {
        static::check('delete', $data);
        $res = Db::use (static::$database)->table(static::$table)->delete($data);
        if ($msg) {
            $res !== null ? suc([], $msg . '成功') : err($msg . '失败');
        }
        return $res;
    }

    public static function get($data = [], $cols = '*', $msg = '')
    {
        static::check('get', $data);
        $res = Db::use (static::$database)->table(static::$table)->cols($cols)->get($data);
        if ($msg) {
            suc($res, $msg . '成功');
        }
        return $res;
    }

    public static function _exec($sql = '')
    {
        return Db::use (static::$database)->exec($sql);
    }

    public static function _find($k = '', $s = null, $v = null)
    {
        return Dbs::use (static::$database)->table(static::$table)->find($k, $s, $v);
    }

    public static function _select($k = '', $s = null, $v = null)
    {
        return Dbs::use (static::$database)->table(static::$table)->select($k, $s, $v);
    }

    public static function _all($cols = '*', $delete = null)
    {
        $where = $delete ? ['deleted_at|null' => true] : [];
        $res   = Db::use (static::$database)->table(static::$table)->cols($cols)->select($where);
        return $res;
    }

    /**
     * 将数组转化为键值对
     * 如果值只有一个就将二维数组转化为一维数组
     * @Author   Wayren
     * @DateTime 2021-11-25T11:50:19+0800
     * @param    string                   $cols [description]
     * @param    string                   $key  [description]
     * @return   [type]                         [description]
     */
    public static function kv($cols = '*', $where = ['deleted_at|null' => true], $fn = null)
    {
        $res = Db::use (static::$database)->table(static::$table)->cols($cols, false)->select($where);
        if (!$res) {
            return [];
        }
        $keys = array_keys(end($res));
        $num  = count($keys);
        foreach ($res as $k => $v) {
            if ($fn) {
                $fn($v);
            }

            $data[$v[$keys[0]]] = $num == 2 ? $v[$keys[1]] : $v;
        }
        return $data;
    }

    public static function _cols($cols = '*', $pear = true)
    {
        return Dbs::use (static::$database)->table(static::$table)->cols($cols, $pear);
    }

}
