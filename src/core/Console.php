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

                lim_mkdir('logs,app,app/api,app/task,app/config,app/config/rule,public',ROOT);

                if (!is_file(ROOT . 'app/api/Api.php')) {
                    copy(ROOT . 'vendor/phplim/core/src/source/Api.php', ROOT . 'app/api/Api.php');
                    wlog('create app/api/Api.php');
                }


                if (!is_file(ROOT . 'app/config/db.php')) {
                    copy(ROOT . 'vendor/phplim/core/src/source/db.php', ROOT . 'app/config/db.php');
                    wlog('create app/config/db.php');
                }

                if (!is_file(ROOT . 'app/config/rule/demo.php')) {
                    copy(ROOT . 'vendor/phplim/core/src/source/demorule.php', ROOT . 'app/config/rule/demo.php');
                    wlog('create app/config/rule/demo.php');
                }

                if (!is_file(ROOT . 'app/function.php')) {
                    copy(ROOT . 'vendor/phplim/core/src/source/function.php', ROOT . 'app/function.php');
                    wlog('create app/function.php');
                }

                if (!is_file(ROOT . 'app/Hook.php')) {
                    copy(ROOT . 'vendor/phplim/core/src/source/Hook.php', ROOT . 'app/Hook.php');
                    wlog('create app/Hook.php');
                }

                if (!is_file(ROOT . 'install.sql')) {
                    copy(ROOT . 'vendor/phplim/core/src/source/install.sql', ROOT . 'install.sql');
                    wlog('create install.sql');
                }

                if (!is_file(ROOT . 'public/index.php')) {
                    copy(ROOT . 'vendor/phplim/core/src/source/index.php', ROOT . 'public/index.php');
                    wlog('create public/index.php');
                }

                if (!is_file(ROOT . 'lim')) {
                    copy(ROOT . 'vendor/phplim/core/src/source/lim', ROOT . 'lim');
                    chmod(ROOT . 'vendor/phplim/core/src/source/lim', 0777);
                    wlog('create lim');
                }

            } else {
                $act = array_shift($argv);
                switch ($act) {
                    case 'build':
                        $sync = 'cp -r ' . dirname(__DIR__) . ' /code/php/core/ && cd /code/php/core/ && git add . && git commit -m \''.time().'\' && git push';
                        shell_exec($sync);
                        wlog('composer sync');
                        break;
                    case 'clear':
                        shell_exec('rm -rf ' . ROOT . 'app');
                        shell_exec('rm -rf ' . ROOT . 'logs');
                        shell_exec('rm -rf ' . ROOT . 'public');
                        shell_exec('rm -rf ' . ROOT . 'install.sql');
                        shell_exec('rm -rf ' . ROOT . 'lim');
                        shell_exec('rm -rf ' . ROOT . '.dev');
                        echo 'clear' . PHP_EOL;
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
                        $pid = file_get_contents('/var/log/' . APP_NAME . '.pid');
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
