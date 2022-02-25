<?
declare (strict_types = 1);
namespace lim;

use \Swoole\Database\PDOConfig;
use \Swoole\Database\PDOPool;

class Dbs
{
    public static $commit = false, $query = [], $sql = '', $pdo = null;
    
    public static function init($db = 'default')
    {
        $c                      = config('db.mysql')[$db];
        Server::$MysqlPool[$db] = new PDOPool((new PDOConfig)
                ->withHost($c['host'])
                ->withPort((int) $c['port'])
                ->withDbName($c['database'])
                ->withCharset($c['charset'])
                ->withUsername($c['username'])
                ->withPassword($c['password'])
                ->withOptions([
                    \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC, //查询模式
                    // \PDO::ATTR_PERSISTENT => true, //长连接
                    \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION, //启用异常模式
                    \PDO::ATTR_STRINGIFY_FETCHES  => false,
                    \PDO::ATTR_EMULATE_PREPARES   => false, //这2个是跟数字相关的设置
                ])
        );
        // wlog($db . ' init');
    }

    public static function pdo($db = 'default')
    {
        try {
            $c   = config('db.mysql')[$db];
            $dsn = "mysql:host={$c['host']};dbname={$c['database']};port={$c['port']};charset={$c['charset']}";
            $opt = [
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_STRINGIFY_FETCHES  => false,
                \PDO::ATTR_EMULATE_PREPARES   => false, //这2个是跟数字相关的设置
            ];
            return new \PDO($dsn, $c['username'], $c['password'], $opt);
        } catch (\Throwable $e) {
            wlog($e->getMessage());
            return null;
        }
    }

    public static function commit($fn, $todo = true)
    {
        static::$commit = true;
        static::$query  = [];
        static::$sql    = '';
        $fn();
        static::$commit = false;

        if ($todo) {
            return static::exec(static::$sql);
        }
        return static::$sql;
        // print_r([static::$query,static::$sql]);

    }

    public static function exec($sql)
    {
        $result = null;

        if (!$pdo = static::pdo(static::$query->database)) {
            return;
        }

        try {
            $pdo->beginTransaction();
            $result = $pdo->exec($sql);
            $pdo->commit();
            wlog($sql);
        } catch (\Throwable $e) {
            $pdo->rollBack();
            if (!str_contains($e->getMessage(), 'Duplicate')) {
                wlog($sql . ' ' . $e->getMessage(), 'db');
            }

            // wlog($sql . ' ' . $e->getMessage(), 'db');
        }
        // Server::$MysqlPool[static::$query->database]->put($pdo);
        $pdo = null;
        return $result;
    }

    public static function query($sql='')
    {
        $result = null;

        if (!$pdo = static::pdo()) {
            return;
        }

        try {
            $pdo->beginTransaction();
            $result = $pdo->query($sql);
            $pdo->commit();
            wlog($sql);
        } catch (\Throwable $e) {
            $pdo->rollBack();
            if (!str_contains($e->getMessage(), 'Duplicate')) {
                wlog($sql . ' ' . $e->getMessage(), 'db');
            }

            // wlog($sql . ' ' . $e->getMessage(), 'db');
        }
        // Server::$MysqlPool[static::$query->database]->put($pdo);
        $pdo = null;
        return $result;
    }

    public static function run($info)
    {
        $result = null;

        if (!$pdo = static::pdo($info->database)) {
            return;
        }

        try {

            $pdo->beginTransaction();
            // wlog($info->sql);

            // $statement = $pdo->prepare($info->sql);
            // if (!$statement) {
            //     throw new RuntimeException('Prepare failed');
            // }

            switch ($info->action) {
                case 'insert':
                    if ($result = $pdo->exec($info->sql)) {
                        $result = (int) $pdo->lastInsertId();
                    }
                    break;
                case 'update':
                    $result = $pdo->exec($info->sql);
                    break;
                case 'delete':
                    $result = $pdo->exec($info->sql);
                    break;
                case 'select':
                    $result = $pdo->query($info->sql)->fetchAll();
                    if (isset($info->jsonCols)) {
                        foreach ($result as $k => $v) {
                            foreach ($info->jsonCols as $value) {
                                if (isset($v[$value])) {
                                    $result[$k][$value] = json_decode($v[$value], true);
                                }
                            }
                        }
                    }
                    break;
                case 'find':
                    // wlog($info->sql);
                    if ($result = $pdo->query($info->sql)->fetch()) {
                        if (isset($info->jsonCols)) {
                            foreach ($info->jsonCols as $v) {
                                if (isset($result[$v])) {
                                    $result[$v] = json_decode($result[$v], true);
                                }
                            }
                        }
                        ksort($result);
                    }
                    break;
                case 'jsonHas':
                    $result = $pdo->query($info->sql)->fetch()['n'];
                    break;
                case 'commit':
                    wlog(static::$sql);
                default:
                    // code...
                    break;
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();

            if (!str_contains($e->getMessage(), 'Duplicate')) {
                wlog($e->getMessage(), 'db');
            } else {
                // wlog($e->getMessage());
            }
            // wlog($e->getMessage());
        }

        $info->run = false;
        // wlog($info->sql);
        return $result;
    }

    public static function __callStatic($method, $args)
    {
        try {
            return call_user_func_array([new dbsQuery(), $method], $args);
        } catch (\Swoole\ExitException $e) {
            print_r($e);
        }
    }
}

class dbsQuery
{
    public $database = 'default', $run = true, $where = [], $sets = [], $cols = '*';

    function use ($database = 'default') {
        $this->database = $database;
        return $this;
    }

    public function debug()
    {
        $this->run   = false;
        $this->debug = true;
        return $this;
    }

    public function table($table = '')
    {
        $this->table = $table;
        return $this;
    }

    public function cols($cols = '*')
    {
        $this->cols = $cols;
        return $this;
    }

    public function limit($value = '')
    {
        return $this;
    }

    public function orderby($key = '', $value = null)
    {
        if (is_string($key)) {
            $this->orderby[] = $key . ' ' . $value;
        }
        foreach ($key as $k => $v) {
            $this->orderby[] = $k . ' ' . $v;
        }
        return $this;
    }

    private function _orderby()
    {
        if (!isset($this->orderby)) {
            return '';
        }
        return 'ORDER BY ' . implode(',', $this->orderby);
    }

    /**
     * JSON操作方法
     * @Author   Wayren
     * @DateTime 2022-02-07T17:45:39+0800
     * @param    string                   $action [description]
     * @param    [type]                   $opt    [description]
     * @return   [type]                           [description]
     */
    public function json($action = '', ...$opt)
    {
        if (is_array($action)) {
            $this->jsonCols = $action;
            return $this;
        }
        $len = count($opt);
        switch ($action) {
            case 'has' && $len == 2:
                list($col, $key) = $opt;
                $this->where[]   = "JSON_CONTAINS_PATH( {$col},'all','$.\"{$key}\"' )";
                $this->action    = 'jsonHas';
                $this->sql       = "SELECT COUNT(*) AS n FROM {$this->table} " . $this->parseWhere();
                return $this->todo();
            case 'set' && $len == 3:
                list($col, $key, $value) = $opt;
                if (is_array($value)) {
                    $value = json_encode($value,256);
                }
                $this->sets[]            = $col . ' = JSON_SET(' . $col . ',\'$.' . $key . '\',\'' . $value . '\') ';
                break;
            case 'unset':
                break;
            case 'cols':
                $this->jsonCols = explode(',', end($opt));
                break;
            default:
                // code...
                break;
        }
        return $this;
    }

    public function jsonSetKeys($keys = '')
    {
        $this->jsonSetKeys = explode(',', $keys);
        return $this;
    }

    public function jsonSet($col, $key, $value = '')
    {
        $this->sets[] = $col . ' = JSON_SET(' . $col . ',\'$."' . $key . '"\',\'' . $value . '\') ';
        return $this;
    }

    public function jsonArrayAppend($key = '', $value = '')
    {
        $this->sets[]  = "{$key} = JSON_ARRAY_APPEND({$key}, '$',$value) ";
        $this->where[] = "!JSON_CONTAINS({$key}, '{$value}')";
        return $this;
    }

    public function jsonHas($key, $v)
    {
        $this->where[] = "JSON_CONTAINS_PATH( {$key},'all','$.\"{$v}\"' )";
        $this->action  = 'jsonHas';
        $this->sql     = "SELECT COUNT(*) AS n FROM {$this->table} " . $this->parseWhere();
        return $this->todo();

    }

    /**
     * 条件解析
     * @Author   Wayren
     * @DateTime 2022-02-09T14:06:59+0800
     * @param    string                   $k [description]
     * @param    [type]                   $s [description]
     * @param    [type]                   $v [description]
     * @return   [type]                      [description]
     */
    public function where($k = '', $s = null, $v = null)
    {
        if (empty($k)) {
            return $this;
        }
        //解析条件数组
        if (is_array($k)) {
            foreach ($k as $key => $v) {

                if (!is_numeric($key)) {
                    $this->parseWhere([$key, $v]);
                    continue;
                }
                $this->parseWhere(is_string($v) ? [$v] : $v);
            }
            return $this;
        }
        //解析单个条件
        $v == null ? ($s == null ? $this->parseWhere([$k]) : $this->parseWhere([$k, $s])) : $this->parseWhere([$k, $s, $v]);
        return $this;
    }

    /**
     * 解析条件
     * @Author   Wayren
     * @DateTime 2022-02-09T12:05:15+0800
     * @param    [type]                   $v [description]
     * @return   [type]                      [description]
     */
    private function parseWhere($v = null)
    {
        //生成SQL语句
        if ($v == null) {
            if ($this->where) {
                return 'WHERE ' . implode(' AND ', $this->where);
            }
            return '';
        }


        //解析条件语句
        $len = count($v);
        switch ($len) {
            case 1:
                $this->where[] = $v[0];
                break;
            case 2:
                $this->where[] = '`'.$v[0] . '` = \'' . $v[1] . '\'';
                break;
            case 3:
                switch (strtolower($v[1])) {
                    case 'like':
                        $this->where[] = $v[0] . ' LIKE \'%' . $v[2] . '%\'';
                        break;
                    case 'in':
                    case 'not in':
                        if (!is_array($v[2])) {
                            return;
                        }
                        $this->where[] = $v[0] . ' ' . strtoupper($v[1]) . ' (\'' . implode('\',\'', $v[2]) . '\')';
                        break;
                    default:
                        $this->where[] = $v[0] . ' ' . $v[1] . ' \'' . $v[2] . '\'';
                        break;
                }
                break;
        }
    }

    public function insert($data = [], $most = false)
    {
        $this->action = 'insert';
        if ($most == false) {
            $data = [$data];
        }
        $this->data = $data;

        $cur = end($data);
        $key = '(`' . implode('`,`', array_keys($cur)) . '`)';

        foreach ($data as $k => $v) {
            $value = [];
            foreach ($v as $kk => $vv) {

                if (is_array($vv)) {
                    $vv = json_encode($vv, 256);
                }

                $value[] = "'" . $vv . "'";
            }
            $po[] = '(' . implode(',', $value) . ')';
        }
        $pos = implode(',', $po);

        // $pos       = '(' . implode(',', array_fill(0, count($cur), '?')) . ')';
        // $pos = implode(',', array_fill(0, count($data), '?'));
        $this->sql = "INSERT INTO {$this->table} {$key} VALUES $pos;";

        return $this->todo();
    }

    public function delete($k = '', $s = null, $v = null)
    {
        $this->where($k, $s, $v);
        $this->sql = "DELETE FROM {$this->table} " . $this->parseWhere();
        print_r($this);
    }

    public function update($data = [], $whereKeys = '')
    {
        $this->action = 'update';

        if ($whereKeys) {
            $where = explode(',', $whereKeys);
            foreach ($where as $key) {
                if (isset($data[$key])) {
                    $this->where($key, $data[$key]);
                }
            }
        }

        foreach ($data as $k => $v) {

            if ($v === null) {
                $tmp[] = '`' . $k . '` = NULL';
                continue;
            }

            //解析JSON
            if (isset($this->jsonSetKeys) && in_array($k, $this->jsonSetKeys)) {
                $this->jsonSet($k, key($v), end($v));
                continue;
            }

            if (is_array($v)) {
                // ksort($v);
                $v = json_encode($v, 256);
            }

            //转义双引号

            $tmp[] = '`' . $k . '` = \'' . $v . '\'';
        }

        $this->sets = array_merge($this->sets, $tmp ?? []);

        $sets = implode(' , ', $this->sets);

        $this->sql = "UPDATE {$this->table} SET {$sets} " . $this->parseWhere() . ";";
        // wlog($this->sql);
        return $this->todo();
    }


    public function select($k = '', $s = null, $v = null)
    {
        $this->where($k, $s, $v);
        $this->action = 'select';
        $this->sql    = "SELECT {$this->cols} FROM {$this->table} " . $this->parseWhere() . $this->_orderby();
        return $this->todo();
    }

    public function find($k = '', $s = null, $v = null)
    {
        $this->where($k, $s, $v);
        $this->action = 'find';
        $this->sql    = "SELECT {$this->cols} FROM {$this->table} " . $this->parseWhere() . $this->_orderby() . " LIMIT 1";
        // wlog($this->sql);
        // print_r($this);
        return $this->todo();
    }

    public function todo()
    {
        if ($this->run) {

            if (Dbs::$commit) {
                Dbs::$sql .= $this->sql;
                Dbs::$query = $this;
            } else {
                return dbs::run($this);
            }

        } else {
            return $this;
            // print_r($this);
        }
    }

    public function __call($method, $args)
    {
        try {
            switch ($method) {
                case 'max':
                    $col = array_shift($args);
                    $result = Dbs::query("SELECT MAX($col) AS $method FROM $this->table")->fetch()[$method];
                    break;
                default:
                    // code...
                    break;
            }

            return $result ?? null;
            
        } catch (Throwable $e) {
            print_r($e);
        }
    }
}
