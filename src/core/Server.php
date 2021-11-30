<?php
declare (strict_types = 1);
namespace lim;

use function Swoole\Coroutine\run;
use \swoole\Timer;

class Server
{
    public static $cache, $ext = [], $server = null, $config, $ini = [], $MysqlPool = null, $RedisPool = null, $MysqlPoolNum = 0;

    public static function run($daemonize = false)
    {
        if (!is_dir(ROOT . 'public')) {
            mkdir(ROOT . 'public', true);
        }

        self::$cache = new \Yac();
        \Swoole\Coroutine::set(['enable_deadlock_check' => null]);
        $config = [

            'reactor_num'           => 1,
            'worker_num'            => (int) WORKER_NUM,
            'task_worker_num'       => (int) TASK_WORKER_NUM,
            'task_enable_coroutine' => true,
            'enable_coroutine'      => true,
            'pid_file'              => '/var/log/' . APP_NAME . '.pid',
            'log_level'             => SWOOLE_LOG_WARNING,
            'hook_flags'            => SWOOLE_HOOK_ALL,
            'max_wait_time'         => 1,
            'reload_async'          => true,
            'package_max_length'    => 100 * 1024 * 1024,
            'max_coroutine'         => (int) MAX_COROUTINE,
            'daemonize'             => $daemonize,
            'document_root'         => ROOT . 'public', // v4.4.0以下版本, 此处必须为绝对路径
            'enable_static_handler' => true,
        ];

        if (method_exists(\app\Hook::class, 'boot')) {
            \app\Hook::boot();
        }

        self::$server         = new \Swoole\WebSocket\Server('0.0.0.0', (int) APP_HW_PORT);
        self::$server->config = config();
        $app                  = new App();
        self::$server->set($config);
        self::$server->on('start', fn() => cli_set_process_title(APP_NAME . '-master'));
        self::$server->on('managerstart', ['\lim\Server', 'managerstart']);
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
        self::loadTasker();

        // wlog('sss');
    }

    public static function WorkerStart($server, int $workerId)
    {

        try {

            Timer::tick(5 * 1000, function () {
                $GLOBALS['config'] = (new \Yac)->get(APP_NAME);
            });
            //清理缓存
            if (function_exists('opcache_reset')) {
                opcache_reset();
            }

            if ($server->taskworker) {
                $id = $workerId - $server->setting['worker_num'];
                if ($id == 0) {

                    if (method_exists(\app\Hook::class, 'task')) {
                        \app\Hook::task();
                    }

                    cli_set_process_title(APP_NAME . '-boot');
                } else {
                    cli_set_process_title(APP_NAME . '-tasker');
                }
            } else {
                cli_set_process_title(APP_NAME . '-worker');
            }
        } catch (\Swoole\ExitException $e) {
            wlog($e->getStatus());
        }

    }

    private static function loadTasker()
    {

        if (APP_ENV == 'dev') {
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
