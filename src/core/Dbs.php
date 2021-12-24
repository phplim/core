<?
declare (strict_types = 1);
namespace lim;

use \Swoole\Database\PDOConfig;
use \Swoole\Database\PDOPool;

class Dbs
{
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
                    \PDO::ATTR_EMULATE_PREPARES   => false,//这2个是跟数字相关的设置
                ])
        );
        wlog($db . ' init');
    }

    public static function commit($fn)
    {
        $fn();
    }

    public static function run($info)
    {
        $result = null;

        if (!isset(Server::$MysqlPool[$info->database])) {
            static::init($info->database);
        }

        $pdo = Server::$MysqlPool[$info->database]->get();

        try {
            $pdo->beginTransaction();
            $statement = $pdo->prepare($info->sql);
            if (!$statement) {
                throw new RuntimeException('Prepare failed');
            }

            switch ($info->action) {
                case 'insert':
                    foreach ($info->data as $key => $v) {
                        array_walk($v, function (&$e) {$e = is_array($e) ? json_encode($e, 256) : $e;});
                        print_r($v);
                        $result = $statement->execute(array_values($v));
                        if (!$result) {
                            throw new RuntimeException('Execute failed');
                        }
                    }
                    break;

                case 'select':
                    if (!$statement->execute()) {
                        throw new RuntimeException('Execute failed');
                    }
                    $result = $statement->fetchAll();
                    break;
                case 'update':
                    if (!$statement->execute()) {
                        throw new RuntimeException('Execute failed');
                    }
                    $result = $statement->rowCount();
                    break;
                default:
                    // code...
                    break;
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            wlog($e->getMessage());
        }

        Server::$MysqlPool[$info->database]->put($pdo);
        $info->run = false;
        wlog($info->sql);
        return $result;
    }

    public static function __callStatic($method, $args)
    {
        try {
            return call_user_func_array([new dbsQuery(), $method], $args);
        } catch (Throwable $e) {
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

    public function jsonSet($col, $key, $value)
    {
        $this->sets[] = $col . ' = JSON_SET(' . $col . ',\'$."' . $key . '"\',\'' . $value . '\') ';
        return $this;
    }

    public function where($key = '', $symbol = null, $value = null)
    {
        if ($value == null && $symbol != null) {
            $value  = $symbol;
            $symbol = '=';
        }
        $this->where[] = $key . ' ' . $symbol . ' "' . $value . '"';
        return $this;
    }

    public function whereIn($col, $data = [])
    {
        $this->where[] = $col . ' IN (\'' . implode('\',\'', $data) . '\')';
        return $this;
    }

    public function whereSql()
    {
        if ($this->where) {
            return 'WHERE ' . implode(' AND ', $this->where);
        }
        return '';
    }

    public function insert($data = [], $most = false)
    {
        $this->action = 'insert';
        if ($most == false) {
            $data = [$data];
        }
        $this->data = $data;

        $cur       = end($data);
        $key       = '(`' . implode('`,`', array_keys($cur)) . '`)';
        $pos       = '(' . implode(',', array_fill(0, count($cur), '?')) . ')';
        $this->sql = "INSERT INTO {$this->table} {$key} VALUES $pos";

        return $this;
    }

    public function delete($data = [])
    {

    }

    public function update($data = [])
    {
        $this->action = 'update';

        foreach ($data as $k => $v) {

            if ($v === null) {
                continue;
            }

            if (is_array($v)) {
                $v = json_encode($v, 256);
            }

            if (is_string($v)) {
                $v = "'" . $v . "'";
            }

            $tmp[] = $k . ' = ' . $v;
        }

        $this->sets = array_merge($this->sets, $tmp ?? []);

        $sets = implode(' , ', $this->sets);

        $this->sql = "UPDATE {$this->table} SET {$sets} " . $this->whereSql();
        return $this->todo();
    }

    public function select()
    {
        $this->action = 'select';
        $this->sql    = "SELECT {$this->cols} FROM {$this->table} " . $this->whereSql();
        return $this->todo();
    }

    public function todo()
    {
        if ($this->run) {
            return dbs::run($this);
        } else {
            print_r($this);
        }
    }

    public function __call($method, $args)
    {
        try {
            print_r([$method, $args]);
        } catch (Throwable $e) {
            print_r($e);
        }
    }
}
