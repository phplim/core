<?
declare (strict_types = 1);
namespace lim;

use \Swoole\Database\PDOConfig;
use \Swoole\Database\PDOPool;

class Db
{

    public static function init($db = 'default')
    {
        $c                 = config('db.mysql')[$db];
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
                    \PDO::ATTR_ERRMODE=>\PDO::ERRMODE_EXCEPTION //启用异常模式
                ])
        );
        // wlog($db.' init');
    }

    public static function json_set(&$data, $keys)
    {
        foreach ($keys as $k => $v) {
            if (isset($data[$v])) {
                foreach ($data[$v] as $kk => $vv) {
                    $data[$v . '|json'] = 'JSON_SET(' . $v . ',\'$."' . $kk . '"\',\'' . $vv . '\')';
                }
                unset($data[$v]);
            }
        }
    }

    public static function json_remove(&$data, $key, $value)
    {
        if (isset($data[$key])) {
            $data[$key . '|json'] = 'JSON_REMOVE(' . $key . ',\'$."' . $data[$key] . '"\')';
            unset($data[$key]);
        }
    }

    public static function json_has(&$data, $keys)
    {
        foreach ($keys as $k => $v) {
            if (isset($data[$v])) {
                $data[$v . '|json_has'] = $data[$v];
                unset($data[$v]);
            }
        }
    }

    public static function json_key(&$data, $keys)
    {
        foreach ($keys as $k => $v) {
            if (isset($data[$v])) {
                $data[$v . '|json_key'] = $data[$v];
                unset($data[$v]);
            }
        }
    }

    public static function __callStatic($method, $args)
    {
        try {
            return call_user_func_array([new query(), $method], $args);
        } catch (Throwable $e) {
            print_r($e);
        }
    }
}

class query
{
    public $pdo, $db = 'default', $limit = '', $cols = '*', $where = '', $order = '', $count = false, $err = null, $orderSql = '', $json = [], $groupBy = '';

    public function __construct()
    {
        $this->use($db = 'default');
    }

    public function use ($db = 'default') {

        if (PHP_SAPI != 'cli') {

            if (isset($this->pdo)) {
                return $this;
            } else {
                $c         = config('db.mysql')[$db];
                $dsn       = "mysql:host={$c['host']};dbname={$c['database']};port={$c['port']};charset={$c['charset']}";
                $opt       = [\PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC];
                $this->pdo = new \PDO($dsn, $c['username'], $c['password'], $opt);
                return $this;
            }
        } else {
            $this->db = $db;
            if (!isset(Server::$MysqlPool[$this->db])) {
                Db::init($db);
            }
            $this->pdo = Server::$MysqlPool[$this->db]->get();
            return $this;
        }
    }

    public function table($table = '')
    {
        $this->table = $table;
        return $this;
    }

    public function order($k = null, $v = null)
    {
        if ($k && is_string($k)) {
            $this->order = " ORDER BY $k ";
        }
        return $this;
    }

    public function cols($value = '', $parse = true)
    {
        if ($parse) {
            $this->cols = '`' . str_replace(',', '`,`', $value) . '`';
        } else {
            $this->cols = $value;
        }

        return $this;
    }

    public function join($arr, $f = 'INNER')
    {
        foreach ($arr as $k => $v) {
            $this->where .= ' ' . $f . ' JOIN ' . $k . ' ON ' . $v;
        }
        $this->join = true;
        return $this;
    }

    public function limit($limit = null)
    {
        return $this;
    }

    public function where($data = null, $f = null, $v = null)
    {
        if (!is_array($data)) {
            if ($v == null) {
                $v = $f;
                $f = '=';
            }
            $this->where .= " WHERE `$data` $f '$v'";
        }

        if (is_array($data)) {
            foreach ($data as $k => $v) {
                if ($k == 'limit') {
                    $limit = $v;
                    continue;
                }

                if ($k == 'page') {
                    $page = $v;
                    continue;
                }

                if ($k == 'ORDER') {
                    foreach ($v as $kk => $vv) {
                        $order[] = "`$kk` $vv";
                    }
                    continue;
                }

                if ($k == 'COUNT') {
                    $this->count = true;
                    continue;
                }

                if ($k == 'IN') {

                    foreach ($v as $kk => $vv) {
                        $in[] = ' ' . $kk . ' IN (\'' . implode('\',\'', $vv) . '\') ';
                    }
                    $where[] = implode(' AND ', $in);
                    continue;
                }

                if ($k == 'MATCH') {
                    foreach ($v as $kk => $vv) {
                        $in[] = 'MATCH (' . $vv . ') AGAINST ("' . $kk . '")';
                    }
                    $where[] = implode(' AND ', $in);
                    continue;
                }

                $key = explode('|', $k);
                if (!isset($key[1])) {
                    //如果条件字段包含.号就不加``号
                    $where[] = str_contains($k, '.') ? "$k = '$v'" : "`$k` = '$v'";
                    continue;
                }

                //解析额外参数
                $where[] = match($key[1]) {
                    'like' => " `$key[0]` LIKE '%$v%'",
                    '>'        => " `$key[0]` > '$v'",
                    '>='       => " `$key[0]` >= '$v'",
                    '<'        => " `$key[0]` < '$v'",
                    '<='       => " `$key[0]` <= '$v'",
                    '<>'       => " `$key[0]` <> '$v'",
                    'json_has' => " JSON_CONTAINS_PATH( {$key[0]},'all','$.\"{$v}\"' )",
                    'json_key' => " {$key[0]}->>'$.\"" . array_keys($v)[0] . "\"' = '" . array_shift($v) . "'",
                    'null'     => " `$key[0]` IS NULL",
                    'notnull'  => " `$key[0]` IS NOT NULL",
                };
            }
            if (isset($where)) {
                $this->where .= ' WHERE ' . implode(' AND ', $where);
            }
        }

        if (isset($limit)) {
            $offset      = (isset($page) && $page > 1 ? $page - 1 : 0) * $limit;
            $this->limit = " LIMIT $offset , $limit";
        }

        if (isset($order)) {
            $this->orderSql = ' ORDER BY ' . implode(',', $order);
        }

        return $this;
    }

    public function incr($key = '', $n = 1)
    {
        $where = implode(' AND ', $this->where);
        $sql   = "UPDATE {$this->table} SET `{$key}` = `{$key}` + {$n} WHERE {$where}";
        return $this->exec($sql);
    }

    //检查数据重复
    public function unique($key = '', $value = '', $where = '')
    {
        $wArr  = explode(',', $where);
        $table = $wArr[0];
        $where = "`{$key}` = '{$value}'";
        if (isset($wArr[1])) {
            $where .= ' AND ' . $wArr[1];
        }
        $sql = "SELECT `{$key}` FROM {$table} WHERE {$where}";

        if ($this->pdo->query($sql)->fetch()) {
            return false;
        }
        return true;
    }

    public function count($data=[])
    {
        if (is_array($data)) {
            $this->where($data);
        } else {
            $this->where = ' WHERE '.$data;
        }
        $sql = "SELECT COUNT(*) AS count FROM {$this->table} {$this->where}";
        
        return $this->query($sql)->fetch()['count'];
    }

    public function delete($data, $v = null)
    {

        $this->where($data);
        $sql = "DELETE FROM {$this->table} {$this->where}";

        return $this->exec($sql);
    }

    public function json($json = [])
    {
        $this->json = $json;
        return $this;
    }

    public function max($col = '')
    {
        $sql = "SELECT MAX({$col}) AS {$col} FROM {$this->table}";
        $ret = $this->query($sql)->fetch();
        return $ret[$col] ?? null;
    }
    public function insert($data)
    {
        $key = implode('`,`', array_keys($data));
        $pos = implode(',', array_fill(0, count($data), '?'));
        $sql = "INSERT INTO {$this->table} (`{$key}`) VALUES ( $pos )";

        $this->execute($sql, $data);
        return $this->id ?? null;
    }

    public function update($data, $pk = ['id'])
    {
        $key = [];
        foreach ($data as $k => $v) {
            //解析更新条件
            if (in_array($k, $pk)) {
                $where[] = "`$k` = '$v'";
                unset($data[$k]);
                continue;
            }

            $kt = explode('|', $k);
            //解析字段
            if (!isset($kt[1])) {
                array_push($key, "`$k`=?");
                continue;
            }
            //解析参数字段
            $t = match($kt[1]) {
                'incr'     => "`$kt[0]` = `$kt[0]` + $v",
                'json'     => " `$kt[0]` = $v",
            };
            array_push($key, $t);
            unset($data[$k]);
        }

        //如果没有key就不更新
        if (empty($key)) {
            // wlog('不更新');
            return null;
        }

        $keyl  = implode(',', $key);
        $where = implode(' AND ', $where);
        $sql   = "UPDATE {$this->table} SET {$keyl} WHERE {$where}";
        // wlog($sql);
        $this->execute($sql, $data);
        $this->pdo = null;
        return $this->status ?? null;

    }

    public function groupBy($value = '')
    {
        $this->groupBy = ' GROUP BY ' . $value . ' ';
        return $this;
    }

    public function get($data)
    {
        if ($data) {
            $this->where($data);
        }

        $sql = "SELECT {$this->cols} FROM {$this->table}" . $this->where . $this->order . ' LIMIT 1';

        $ret       = $this->query($sql)->fetch();
        if ($ret && substr_count($this->cols, ',') == 0 && $this->cols != '*') {
            return end($ret);
        }
        return $ret;
    }

    public function select($data = [])
    {
        if (is_array($data)) {
            $this->where($data);
        } else {
            $this->where = ' WHERE '.$data;
        }
        $sql = "SELECT {$this->cols} FROM {$this->table}" . $this->where . $this->groupBy . $this->orderSql . $this->limit;
       
        $data      = $this->query($sql)->fetchAll();
        $this->pdo = null;
        if ($this->count) {
            $total = $this->query("SELECT COUNT(*) AS t FROM {$this->table}" . $this->where)->fetch()['t'];
            return [(int) $total, $data];
        }

        return $data;
    }

    public function execute($sql, $data)
    {
        array_walk($data, function (&$e) {$e = is_array($e) ? json_encode($e, 256) : $e;});
        try {
            $st           = $this->pdo->prepare($sql);
            $this->status = $st->execute(array_values($data));
            $this->id     = $this->pdo->lastInsertId();
        } catch (\PDOException $e) {
            if (!str_contains($e->getMessage(), 'Duplicate')) {
                wlog($sql . ' ' . $e->getMessage(), 'db');
            }
            return null;
            // exit(json_encode(['code'=>(int) $e->getCode(),'msg'=> $e->getMessage()]));
        }

    }

    public function exec($sql)
    {

        try {
            $s = $this->pdo->exec($sql);
            return $s;
        } catch (\PDOException $e) {
            if (!str_contains($e->getMessage(), 'Duplicate')) {
                wlog($sql . ' ' . $e->getMessage(), 'db');
            }
            // err($e->getMessage());
            // wlog($sql);
        }
    }

    public function query($sql)
    {
        try {
            return $this->pdo->query($sql);
        } catch (\PDOException $e) {
            wlog($e->getMessage(), 'db');
            // err($e->getMessage());
        }
    }

    public function __destruct()
    {
        if (PHP_SAPI != 'cli'){
            $this->pdo=null;
        } else {
            Server::$MysqlPool[$this->db]->put($this->pdo);
            // wlog('put');
        }
        
    }
}
