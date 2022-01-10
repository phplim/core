<?
declare (strict_types = 1);
namespace lim;


class Data
{

    public static $user = false, $database = APP_ENV, $table = null, $rule = null ,$data=[],$config=[];

    public static function init($table='')
    {
        static::$table = $table;
        static::$rule = $table;
        wlog('data init');
        return self::class;
    }

    // public static function config($key='')
    // {
    //     wlog(__CLASS__);
    //     return Server::$server->DataConfig['AttendanceModifylog'][$key];
    // }

    public static function table($table='')
    {
        static::$table = $table;
        return self::class;
    }

    public static function check($method, &$data)
    {

        if ($rule = Server::$extend['rule'][strtolower((string) static::$rule)][$method] ?? null) {
            
            $vars = array_keys($rule);
            //过滤非法参数
            foreach ($data as $k => $v) {

                //排除表达式过滤
                if (preg_match('/\|/',$k)) {
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

    public static function insert($data = [], $msg = '')
    {
        static::check('insert', $data);
        $res = Db::use (static::$database)->table(static::$table)->insert($data);
        if ($msg) {
            $res !== null ? suc($res, $msg . '成功') : err($msg . '失败');
        }
        return $res;
    }

    public static function update($data, $where, $msg = '')
    {
        static::check('update', $data);
        $res = Db::use (static::$database)->table(static::$table)->update($data, $where);
        if ($msg) {
            $res !== null ? suc([], $msg.'成功') : err($msg.'失败');
        }
        return $res;
    }

    public static function delete($data, $msg = '')
    {
        static::check('delete', $data);
        $res = Db::use (static::$database)->table(static::$table)->delete($data);
        if ($msg) {
            $res !== null ? suc([], $msg.'成功') : err($msg.'失败');
        }
        return $res;
    }

    public static function get($data = [], $cols = '*', $msg = '')
    {
        static::check('get', $data);
        $res = Db::use (static::$database)->table(static::$table)->cols($cols)->get($data);
        if ($msg) {
            suc($res, $msg.'成功');
        }
        return $res;
    }

    public static function _get($cols,$data=[],$check=true)
    {
        if ($check) {
            static::check('get', $data);
        }
        
        return Db::use (static::$database)->table(static::$table)->cols($cols,false)->get($data);
    }

    public static function search($data = [], $cols = '*', $msg = '')
    {
        static::check('search', $data);
        $res = Db::use (static::$database)->table(static::$table)->cols($cols)->select($data);
        if ($msg) {
            suc($res, $msg.'成功');
        }
        return $res;
    }

    public static function cols($cols='*',$pear=true)
    {
        return Db::use(static::$database)->table(static::$table)->cols($cols,$pear);
    }

    public static function exec($sql='')
    {
        return Db::use(static::$database)->exec($sql);
    }

    public static function find($v='',$k='id')
    {
        return Db::use(static::$database)->table(static::$table)->get([$k=>$v]);
    }

    public static function all($cols='*',$delete=null)
    {
        $where = $delete ? ['deleted_at|null'=>true] :[];
        $res=Db::use(static::$database)->table(static::$table)->cols($cols)->select($where);
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
    public static function kv($cols='*',$where = ['deleted_at|null'=>true],$fn = null )
    {
        $res = Db::use (static::$database)->table(static::$table)->cols($cols,false)->select($where);
        if (!$res) {
            return[];
        }
        $keys = array_keys(end($res));
        $num = count($keys);
        foreach ($res as $k => $v) {
            if($fn) $fn($v);
            $data[$v[$keys[0]]]= $num==2 ? $v[$keys[1]] :$v;
        }
        return $data;
    }

}


// /**
//  * 数据处理类
//  */
// class Data
// {
//     public static $user = false, $database = APP_ENV, $table = null, $rule = null ,$data=[];

//     public static function __callStatic($method, $args)
//     {
//         try {
//             return call_user_func_array([new DataQuery(), $method], $args);
//         } catch (Throwable $e) {
//             print_r($e);
//         }
//     }
// }


// class DataQuery  
// {
    
//     public  function cols($cols='*',$pear=true)
//     {
//         return Db::use(Data::$database)->table(Data::$table)->cols($cols,$pear);
//     }
// }
