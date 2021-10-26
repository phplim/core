<?php
declare (strict_types = 1);
namespace lim;

use \swoole\Timer;

class Server
{
    public static $role = null, $route = null, $cache, $server = null, $config, $ini = [], $MysqlPool = null, $RedisPool = null;

    public static function run($daemonize = false)
    {
        self::$cache = new \Yac();

        if (is_file(ROOT . 'app.ini')) {
            self::$ini = parse_ini_file(ROOT . 'app.ini', true);
        }

        \app\loader::init();

        self::$config                     = config('server');
        self::$config['set']['daemonize'] = $daemonize;

        self::$server = new \Swoole\WebSocket\Server(self::$config['ip'], (int) self::$config['http']);
        $app          = new App();
        self::$server->set(self::$config['set']);
        self::$server->on('start', fn() => cli_set_process_title(self::$config['name'] . '-master'));
        // self::$server->on('managerstart', fn() => self::loadTasker());
        self::$server->on('managerstart', ['\lim\Server','managerstart']);
        self::$server->on('WorkerStart', ['\lim\Server', 'WorkerStart']);
        self::$server->on('WorkerStop', fn() => Timer::clearAll());
        self::$server->on('BeforeReload', fn() => self::loadTasker());
        self::$server->on('task', ['\lim\App', 'task']);
        self::$server->on('open', [$app, 'open']);
        self::$server->on('message', [$app, 'message']);
        self::$server->on('close', fn() => '');

        self::$server->on('request', [$app, 'request']);

        // $udp = self::$server->listen($config['ip'], (int) $config['udp'], SWOOLE_SOCK_UDP);
        // $udp->on('packet', ['\lim\App', 'udper']);
        self::$server->start();
    }

    public static function managerstart($server)
    {

        // wlog('sss');
    }

    public static function WorkerStart($server, int $workerId)
    {
        try {
            //清理缓存
            if (function_exists('opcache_reset')) {
                opcache_reset();
            }

            

            if ($server->taskworker) {
                $id = $workerId - $server->setting['worker_num'];
                if ($id == 0) {
                    cli_set_process_title(self::$config['name'] . '-boot');
                    if (class_exists('\app\loader')) {
                        $loader = new \app\loader;
                        // $loader->init();
                        if (!isset(self::$ini['dev']) && method_exists($loader, 'run')) {
                            $loader->run();
                        }
                    }
                } else {
                    cli_set_process_title(self::$config['name'] . '-tasker');
                }
            } else {
                cli_set_process_title(self::$config['name'] . '-worker');
            }
        } catch (\Swoole\ExitException $e) {
            wlog($e->getStatus());
        }

    }

    private static function loadTasker()
    {
       
        if (isset(self::$ini['dev'])) {
            return;
        }

        Timer::clearAll();
        //时间,延迟,天,周
        if ($tasker = config('task')) {
            foreach ($tasker as $k => $v) {
                $arr   = explode(',', $v);
                $space = $arr[0] ?? 0;
                $begin = $arr[1] ?? 0;
                $day   = $arr[2] ?? null;

                if ($day) {
                    $now = ((time() + 28800) % 86400);
                    if ($now > $day) {
                        $after = 86400 - $now + $day + $begin;
                    } else {
                        $after = $day - $now + $space;
                    }
                } else {
                    $after = $space - (time() + 28800) % $space + $begin;
                }
                wlog(date('Y-m-d H:i:s', time() + $after) . " 后每隔 " . sprintf('% 5d', $space) . " 秒循环执行 {$k}", 'task');
                Timer::after(1000 * $after, function () use ($k, $space) {
                    self::$server->task(['run' => $k]);
                    Timer::tick(1000 * $space, function () use ($k) {
                        self::$server->task(['run' => $k]);
                    });
                });
            }
        }
    }
}
