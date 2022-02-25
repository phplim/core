<?
declare (strict_types = 1);
namespace lim;

/**
 *
 */
class Dbr
{

    public static $opt =  [
        'database'=>'default',
        'table'=>null,
        'where'=>null,
        'field'=>'*'
    ];

    public function __construct()
    {
        // code...
    }

    // public static function table($value='')
    // {
    //     // self::$table = $value;
    //     return self::class;
    // }

    public function insert($value = '')
    {
        // code...
    }

    public function find($value = '')
    {
        // code...
    }

    public function exec($value = '')
    {
        // code...
    }

    public static function __callStatic($method, $args)
    {
        try {
            wlog('static '.$method);
            switch ($method) {
                case 'use':
                    wlog('use');
                    break;
                case 'exec':
                    break;
                case 'table':
                    self::$opt['table']=array_shift($args);
                    break;
                default:
                    break;
            }
            // print_r(['static',$method, $args]);
            return new self($args);
        } catch (\Swoole\ExitException $e) {
            print_r($e);
        }
    }

    public function __call($method, $args)
    {
        try {
            
            $where = self::$opt['where']??'';
            wlog('call '.$method);
            switch ($method) {
                case 'where':
                    self::$opt['where'] = $args;
                    return $this;
                case 'field':
                    self::$opt['cols'] = $args;
                    return $this;
                case 'insert':
                    self::$opt['value'] = $args;
                    return $this;
                case 'update':
                    self::$opt['value'] = $args;
                    return $this;
                case 'delete':
                    self::$opt['value'] = $args;
                    return $this;
                case 'select':
                    self::$opt['value'] = $args;
                    return $this;
                case 'find':
                    self::$opt['value'] = $args;
                    return $this;

                //聚合查询
                case 'max':
                case 'min':
                case 'count':
                case 'sum':
                case 'avg':
                    $col = $method == 'count' ? '*' : array_shift($args);
                    $table = self::$opt['table']??'';
                    $act = strtoupper($method);
                    $sql = "SELECT $act($col) AS $method FROM $table LIMIT 1;";
                    wlog($sql);
                    return $this;

                case 'orderby':
                    self::$opt['value'] = $args;
                    return $this;
                case 'limit':
                    self::$opt['value'] = $args;
                    return $this;
                case 'join':
                    self::$opt['value'] = $args;
                    return $this;
                case 'table':
                    self::$opt['table'] = array_shift($args);

                    return $this;

                default:
                    return $this;
                    break;
            }
            // print_r(['call',$method, $args]);
        } catch (\Swoole\ExitException $e) {
            print_r($e);
        }
    }

}
