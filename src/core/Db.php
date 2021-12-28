<?
declare (strict_types = 1);
namespace lim;

use \Swoole\Database\PDOConfig;
use \Swoole\Database\PDOPool;

class Db
{
    public static $pool = [], $poolNum = [];
    public static function init($db = 'default')
    {
        try {
            $c               = config('db.mysql')[$db];
            self::$pool[$db] = new PDOPool((new PDOConfig)
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
                        \PDO::ATTR_EMULATE_PREPARES   => false,
                    ])
            );
            // wlog($db . ' init');
        } catch (\Throwable $e) {
            print_r($e);
            // exit;
        }
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

    public static function pdo($db)
    {
        $dsn = "mysql:host={$db['host']};dbname={$db['database']};port={$db['port']};charset={$db['charset']}";
        $opt = [\PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC, \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION];
        return new \PDO($dsn, $db['username'], $db['password'], $opt);
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
    public $log = false, $pdo, $db = 'default', $limit = '', $cols = '*', $where = '', $order = '', $count = false, $err = null, $orderSql = '', $json = [], $groupBy = '';

    public function __construct()
    {
        // $this->db = 'default';
        $this->use();
    }

    public function init()
    {
        // wlog('pdo init '.$this->db);
        try {
            $c   = config('db.mysql')[$this->db];
            $dsn = "mysql:host={$c['host']};dbname={$c['database']};port={$c['port']};charset={$c['charset']}";
            $opt = [
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC, //查询模式
                // \PDO::ATTR_PERSISTENT => true, //长连接
                \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION, //启用异常模式
                \PDO::ATTR_STRINGIFY_FETCHES  => false,
                \PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            return new \PDO($dsn, $c['username'], $c['password'], $opt);
        } catch (\Throwable $e) {
            wlog($e->getMessage());
            return null;
        }
    }

    function use ($db = 'default') {
        $this->db = $db;
        return $this;

        if (PHP_SAPI != 'cli') {
            if (!isset($this->pdo)) {
                $c         = config('db.mysql')[$db];
                $dsn       = "mysql:host={$c['host']};dbname={$c['database']};port={$c['port']};charset={$c['charset']}";
                $opt       = [\PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC, \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION];
                $this->pdo = new \PDO($dsn, $c['username'], $c['password'], $opt);
            }
        } else {
            $this->db = $db;
            if (!isset(Db::$pool[$this->db]) || !isset(Db::$poolNum[$this->db]) || Db::$poolNum[$this->db] <= 1) {
                Db::$poolNum[$this->db] = 60;
                Db::init($db);
            }
            $this->pdo = Db::$pool[$this->db]->get();
            Db::$poolNum[$this->db]--;
        }
        return $this;
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
        if ($value == '*' || empty($value)) {
            $this->cols = "*";
            return $this;
        }

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
                if ($k == 'limit' || $k == 'page_size') {
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
                    '>'        => " $key[0] > '$v'",
                    '>='       => " $key[0] >= '$v'",
                    '<'        => " $key[0] < '$v'",
                    '<='       => " $key[0] <= '$v'",
                    '<>'       => " $key[0] <> '$v'",
                    'json_has' => " JSON_CONTAINS_PATH( {$key[0]},'all','$.\"{$v}\"' )",
                    'json_key' => " {$key[0]}->>'$.\"" . array_keys($v)[0] . "\"' = '" . array_shift($v) . "'",
                    'null'     => " $key[0] IS NULL",
                    'notnull'  => " $key[0] IS NOT NULL",
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

    public function count($data = [])
    {
        if (is_array($data)) {
            $this->where($data);
        } else {
            $this->where = ' WHERE ' . $data;
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

    public function log()
    {
        $this->log = true;
        return $this;
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
            return true;
        }

        $keyl  = implode(',', $key);
        $where = implode(' AND ', $where);
        $sql   = "UPDATE {$this->table} SET {$keyl} WHERE {$where}";

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

        if (isset($this->cacheKey)) {
            if ($data = Server::$cache->get($this->cacheKey)) {
                return $data;
            }
        }

        if ($data) {
            $this->where($data);
        }

        $sql = "SELECT {$this->cols} FROM {$this->table}" . $this->where . $this->order . ' LIMIT 1';

        $ret = $this->query($sql)->fetch();
        if ($ret && substr_count($this->cols, ',') == 0 && $this->cols != '*') {
            return end($ret);
        }
        if (isset($this->cacheKey)) {
            Server::$cache->set($this->cacheKey, $ret, $this->cacheExp);
        }
        return $ret;
    }

    public function select($data = [], $call = null)
    {

        if (is_array($data)) {
            $this->where($data);
        } else {
            $this->where = ' WHERE ' . $data;
        }
        $sql = "SELECT {$this->cols} FROM {$this->table}" . $this->where . $this->groupBy . $this->orderSql . $this->limit;

        $data = $this->query($sql)->fetchAll();

        if ($this->count) {
            $total = $this->query("SELECT COUNT(*) AS t FROM {$this->table}" . $this->where)->fetch()['t'];
            return [(int) $total, $data];
        }

        if ($call !== null) {
            foreach ($data as $k => $v) {
                $call($v);
            }
        }

        return $data;
    }

    public function execute($sql, $data)
    {
        if (!$this->pdo = $this->init()) {
            return null;
        }
        
        array_walk($data, function (&$e) {$e = is_array($e) || is_object($e) ? json_encode($e, 256) : $e;});

        if (APP_ENV == 'dev') {
            wlog($sql);
            print_r($data);
        }

        try {
            $this->pdo->beginTransaction();
            $st           = $this->pdo->prepare($sql);
            $this->status = $st->execute(array_values($data));
            $this->id     = $this->pdo->lastInsertId();
            $this->pdo->commit();
        } catch (\PDOException $e) {
            $this->pdo->rollBack();
            if (!str_contains($e->getMessage(), 'Duplicate')) {
                wlog($sql . ' ' . $e->getMessage(), 'db');
            }
            if (APP_ENV == 'dev') {
                wlog($sql . ' ' . $e->getMessage(), 'db');
            }
            return null;
        }
    }

    public function exec($sql)
    {
        if (!$this->pdo = $this->init()) {
            return null;
        }

        if (APP_ENV == 'dev') {
            wlog($sql);
        }
        try {
            $this->pdo->beginTransaction();
            $s = $this->pdo->exec($sql);
            $this->pdo->commit();
            return $s;
        } catch (\PDOException $e) {
            $this->pdo->rollBack();
            if (!str_contains($e->getMessage(), 'Duplicate')) {
                wlog($sql . ' ' . $e->getMessage(), 'db');
            }
            return null;
        }
    }

    public function query($sql)
    {
        if (!$this->pdo = $this->init()) {
            return null;
        }

        if (APP_ENV == 'dev') {
            wlog($sql);
        }

        try {
            return $this->pdo->query($sql);
        } catch (\PDOException $e) {
            wlog($e->getMessage(), 'db');
            // err($e->getMessage());
        }
    }

    public function __destruct()
    {
        $this->pdo = null;
        // wlog('pdo pull '.$this->db);
        if (PHP_SAPI != 'cli') {
            $this->pdo = null;
        } else {

            // Db::$pool[$this->db]->put($this->pdo);
        }
    }
}
