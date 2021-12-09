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
                    \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION, //启用异常模式
                ]),5
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
            self::init($info->database);
        }

        // print_r($info);
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
                    wlog('up:'.$statement->rowCount());
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
            return call_user_func_array([new query1(), $method], $args);
        } catch (Throwable $e) {
            print_r($e);
        }
    }
}

class query1
{
    public $database = 'default', $run = true;

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

    public function jsonSet()
    {
        // code...
    }

    public function where($where)
    {
        $this->where = $where;
        return $this;
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
        $this->sql    = "UPDATE {$this->table} SET face = '1s1' WHERE {$this->where}";
        return $this->todo();
    }

    public function select($data = [])
    {
        $this->action = 'select';
        $this->sql    = "SELECT {$this->cols} FROM {$this->table}";
        return $this->todo();
    }

    public function todo()
    {
        if ($this->run) {
            return dbs::run($this);
        } else {
            // print_r($this);
        }
    }

    public function __call($method, $args)
    {
        try {
            print_r([$method, $args]);
            // unset($this->table);
            // dbs::$method($this);
            // wlog('do');
        } catch (Throwable $e) {
            print_r($e);
        }
    }
}
