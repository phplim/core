<?php
declare (strict_types = 1);
namespace lim;

use \Swoole\Database\RedisConfig;
use \Swoole\Database\RedisPool;

class Redis
{

    public static function init($db = 'default')
    {
        $c                      = config('db.redis')[$db];
        Server::$RedisPool[$db] = new RedisPool((new RedisConfig)
                ->withHost($c['host'])
                ->withPort((int) $c['port'])
                ->withAuth($c['pass'] ?? '')
                ->withDbIndex(0)
                ->withTimeout(1)
        );
        wlog($db . ' init');
    }

    // public static function __callStatic($method, $args)
    // {
    //     try {
    //         return call_user_func_array([(new RedisQuery())->redis, $method], $args);
    //     } catch (Throwable $e) {
    //         print_r($e);
    //     }
    // }

    public static function __callStatic($method, $args)
    {
        try {
                $c           = config('db.redis')['default'];
                $redis = new \Redis();
                $redis->connect($c['host'], (int) $c['port']);
                $redis->auth($c['pass']);
            return call_user_func_array([$redis, $method], $args);
        } catch (Throwable $e) {
            print_r($e);
        }
    }
}

class RedisQuery
{
    public $redis=null,$db='default';

    public function __construct()
    {
        $this->use($db = 'default');
    }

    function use ($db = 'default') {

        if (PHP_SAPI != 'cli') {

            if (!isset($this->redis)) {
                $c           = config('db.redis')['default'];
                $this->redis = new \Redis();
                $this->redis->connect($c['host'], (int) $c['port']);
                $this->redis->auth($c['pass']);
            }
        } else {
            $this->db = $db;
            if (!isset(Server::$RedisPool[$this->db])) {
                \lim\Redis::init($db);
            }
            $this->redis = Server::$RedisPool[$this->db]->get();
        }
    }

    public function __destruct()
    {
        if (PHP_SAPI == 'cli'){
            Server::$RedisPool[$this->db]->put($this->redis);
        }
    }
}
