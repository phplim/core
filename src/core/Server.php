<?php
declare (strict_types = 1);
namespace lim;

use function Swoole\Coroutine\run;
use \swoole\Timer;

class Server
{
    public static $io, $extend, $cache = null, $ext = [], $server = null, $config, $ini = [], $MysqlPool = null, $RedisPool = null, $MysqlPoolNum = 0;

    public static function run($daemonize = false)
    {
        if (!static::$cache) {
            static::$cache = new \Yac(APP_NAME);
            wlog('初始化Yac');
        }

        static::$io = new \stdClass;

        if (method_exists(\app\Hook::class, 'boot')) {
            \app\Hook::boot();
        }

        if (!is_dir(ROOT . 'public')) {
            mkdir(ROOT . 'public', true);
        }

        \Swoole\Coroutine::set(['enable_deadlock_check' => null, 'hook_flags' => SWOOLE_HOOK_ALL]);
        // \Co::set([]);
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
            'package_max_length'    => 5 * 1024 * 1024,
            'max_coroutine'         => (int) MAX_COROUTINE,
            'daemonize'             => $daemonize,
            'document_root'         => ROOT . 'public',
            'enable_static_handler' => true,
        ];

        self::$server = new \Swoole\WebSocket\Server('0.0.0.0', (int) APP_HW_PORT);
        $app          = new App();
        self::$server->set($config);
        self::$server->on('start', fn() => cli_set_process_title(APP_NAME . '-Master'));
        self::$server->on('managerstart', ['\lim\Server', 'managerstart']);
        self::$server->on('WorkerStart', ['\lim\Server', 'WorkerStart']);
        // self::$server->on('WorkerStop', fn() => '');
        self::$server->on('AfterReload', fn() => self::loadTasker());
        // self::$server->on('task', ['\lim\App', 'task']);

        self::$server->on('task', ['\lim\Server', 'task']);

        self::$server->on('open', [$app, 'open']);
        self::$server->on('message', [$app, 'message']);
        self::$server->on('close', fn() => '');
        self::$server->on('request', [$app, 'request']);

        $tcp = self::$server->listen("0.0.0.0", APP_HW_PORT - 1, SWOOLE_SOCK_TCP);
        $tcp->set([]);
        $tcp->on('receive', function ($server, $fd, $reactor_id, $data) {

            if (!$res = json_decode((string) $data, true)) {
                // wlog('tcp请求非法');
                return;
            }

            if (!isset($res['token']) || !App::token($res['token'], true)) {
                // wlog('token非法');
                return;
            }

            if (!$action = $res['action'] ?? null) {
                // wlog('tcp无动作');
                return;
            }

            switch ($action) {
                case 'sync':
                    $script = "cd " . ROOT . " && git pull --no-rebase;";
                    $e      = shell_exec($script);
                    $server->reload();
                    // wlog($e);
                    $server->send($fd, json_encode(['code' => 1, 'message' => '代码更新且服务重启成功'], 256));
                    break;
                case 'reload':
                    $server->reload();
                    $server->send($fd, json_encode(['code' => 1, 'message' => '服务重启成功'], 256));
                    break;
                case 'clear':
                    $d   = date('Y-m');
                    $dir = ROOT . 'logs/';
                    if ($handle = opendir($dir)) {
                        while (($file = readdir($handle)) !== false) {
                            if (($file == ".") || ($file == "..") || is_dir($dir . $file)) {
                                continue;
                            }

                            if (!str_starts_with($file, $d)) {
                                $path = $dir . 'data/bak/' . substr($file, 0, 7) . '/';
                                if (!is_dir($path)) {
                                    mkdir($path, 0777, true);
                                }
                                rename($dir . $file, $path . $file);
                            }
                        }
                        closedir($handle);
                    }
                    $server->send($fd, json_encode(['code' => 0, 'message' => '整理日志成功'], 256));
                    break;
                default:
                    // wlog('未知任务');
                    $server->send($fd, json_encode(['code' => 0, 'message' => '未知任务'], 256));
                    break;
            }
        });

        self::$server->start();
    }

    public static function managerstart($server)
    {
        cli_set_process_title(APP_NAME . '-Manager');
        self::loadTasker();
        // self::loadTasker();
        wlog("服务启动成功");
    }

    public static function WorkerStart($server, int $workerId)
    {

        try {

            Timer::clearAll();
            if (function_exists('opcache_reset')) {
                opcache_reset();
            }
            self::$cache->flush();

            Configer::init();

            static::$extend = uc('config');
            // wlog('缓存配置');

            //同步配置文件
            // Timer::tick(10 * 1000, fn() => static::$extend = uc('config'));

            if ($server->taskworker) {
                $id = $workerId - $server->setting['worker_num'];

                if ($id == 0) {
                    // print_r(get_defined_constants(true)['user']);
                    // print_r(get_included_files());
                }
                cli_set_process_title(APP_NAME . '-Tasker');
            } else {
                cli_set_process_title(APP_NAME . '-Worker');
            }
        } catch (\Swoole\ExitException $e) {
            wlog($e->getStatus());
        }

    }

    /**
     * 定时任务
     * @Author   Wayren
     * @DateTime 2021-10-08T16:23:58+0800
     * @param    [type]                   $server [description]
     * @param    [type]                   $task   [description]
     * @return   [type]                           [description]
     */
    public static function task($server, $task)
    {
        if (isset($task->data['run'])) {
            objRun($task->data['run']);
        }
    }

    private static function loadTasker()
    {

        Timer::clearAll();
        sleep(2);
        wlog('加载定时任务');
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

    public static function __callStatic($method, $args)
    {
        switch ($method) {
            case 'reload':
                self::$server->reload();
                break;

            default:
                // code...
                break;
        }
        // $res = lim_tcp('127.0.0.1' . ':' . (APP_HW_PORT - 1), ['token' => APP::token(), 'action' =>$method]);
        // wlog($res);
    }
}
