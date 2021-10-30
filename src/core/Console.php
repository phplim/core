<?php
declare (strict_types = 1);

namespace lim;

use function Swoole\Coroutine\run;
use \lim\Server;
class Console
{

    function __construct($argv)
    {

        /**
         *
         * S: websocket服务
         * d  后台运行
         * p: 指定端口
         * F: 运行指定函数
         * O: 实例化对象 .号分割路径
         * M: 运行类方法
         *
         *
         * */

        $sortOpts = 'S:dp:F:O:C:M:';
        $opter    = getopt($sortOpts);

        $optArr = [];
        foreach ($opter as $k => $v) {
            array_push($optArr, '-' . $k);
            array_push($optArr, $v);
        }
        array_shift($argv);
        $argv = array_diff($argv, $optArr);

        try {
            $this->run($opter, $argv);
        } catch (\Throwable $e) {
            print_r($e);
        }

    }

    public function run($o, $argv)
    {


        if (empty($o)) {
            if (empty($argv)) {

                echo "Less is More!" . PHP_EOL;
                echo PHP_EOL . '复制下面的命令并执行以便快速运行' . PHP_EOL;
                $s = 'alias ' . config('server')['name'] . '=\'/www/server/php/80/bin/php ' . __FILE__ . '\'';
                echo $s . PHP_EOL . PHP_EOL;

                if (!is_dir(ROOT . 'logs')) {
                    mkdir(ROOT . 'logs', 0777);
                    wlog('mkdir logs');
                }

                if (!is_dir(ROOT . 'public')) {
                    mkdir(ROOT . 'public', 0777);
                    copy(ROOT . 'vendor/phplim/core/src/source/index.php', ROOT . 'public/index.php');
                    wlog('mkdir public copy index.php');
                }

            } else {
                $act = array_shift($argv);
                switch ($act) {
                    case 'build':
                            $sync = 'cp -r '.dirname(__DIR__).' /code/php/core/';
                        shell_exec($sync);
                        wlog($sync);
                        break;

                    default:
                        echo 'aaa';
                        break;
                }
            }
        }

        //运行函数
        if ($fn = $o['F'] ?? null) {
            try {
                run(fn() => $fn(...$argv));
            } catch (\Swoole\ExitException $e) {
                print_r($e);
            }

        }

        //运行对象
        if ($obj = $o['O'] ?? null) {
            run(fn() => objRun($o['O'], ...$argv));
        }

        //操作服务
        if (isset($o['S'])) {
            switch ($o['S']) {
                case 'run':

                    if (isset($o['d'])) {
                        \lim\Server::run(true);
                    } else {
                        Server::run();
                        return;
                    }
                    break;
                case 'stop':
                case 'reload':
                    //清理缓存
                    if (function_exists('opcache_reset')) {
                        opcache_reset();
                        // wlog('opcache清理');
                    }
                    run(function () use ($o) {
                        \swoole\timer::clearAll();
                        $pid = file_get_contents('/var/log/' . config('server.name') . '.pid');
                        $num = $o['S'] == 'stop' ? 15 : 10;
                        $n   = [15 => '停止', 10 => '重启'];
                        $ret = \co::exec('kill -' . $num . ' ' . $pid);
                        if ($ret['code'] === 0) {
                            echo "服务" . $n[$num] . "成功\n";
                        }
                    });
                    break;
            }
        }

    }
}
