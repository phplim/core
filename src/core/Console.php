<?php
declare (strict_types = 1);

namespace lim;

use function Swoole\Coroutine\run;
use \lim\Server;

class Console
{

    function __construct($argv)
    {

        // array_shift($argv);

        // if (!$action = array_shift($argv)) {
        //     wlog('没有可用命令');
        //     return;
        // }

        // try {

        //     switch ($action) {
        //         case 'fn':
        //             Configer::init();
        //             $fn = array_shift($argv);
        //             run(fn() => $fn(...$argv));
        //             break;
        //         case 'obj':
        //             // code...
        //             break;
        //         default:
        //             // code...
        //             break;
        //     }

        // } catch (\Swoole\ExitException $e) {
        //     print_r($e);
        // }

        // return;

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
            Configer::define();
            // print_r(get_included_files());
            if (empty($argv)) {

                echo "Less is More!" . PHP_EOL;

                lim_mkdir('logs,app,app/api,app/task,app/config,app/config/rule,public', ROOT);

                if (!is_file(ROOT . 'app/api/Api.php')) {
                    copy(ROOT . 'vendor/phplim/core/src/source/Api.php', ROOT . 'app/api/Api.php');
                    wlog('create app/api/Api.php');
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

                // if (!is_file(ROOT . 'install.sql')) {
                //     copy(ROOT . 'vendor/phplim/core/src/source/install.sql', ROOT . 'install.sql');
                //     wlog('create install.sql');
                // }

                if (!is_file(ROOT . 'public/index.php')) {
                    copy(ROOT . 'vendor/phplim/core/src/source/index.php', ROOT . 'public/index.php');
                    wlog('create public/index.php');
                }

                // if (!is_file(ROOT . 'lim')) {
                //     copy(ROOT . 'vendor/phplim/core/src/source/lim', ROOT . 'lim');
                //     chmod(ROOT . 'vendor/phplim/core/src/source/lim', 0777);
                //     wlog('create lim');
                // }

            } else {
                $act = array_shift($argv);
                switch ($act) {
                    case 'build':
                        $sync = 'cp -r ' . dirname(__DIR__) . ' /code/php/core/ && cd /code/php/core/ && git add . && git commit -m \'' . time() . '\' && git push';
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
                    case 'server':

                        if (!defined('SYNC_HOST')) {
                            wlog('非本地环境');
                            return;
                        }

                        if (!$host = SYNC_HOST[array_shift($argv)] ?? null) {
                            wlog('目标环境未配置');
                            return;
                        }

                        if (!$action = array_shift($argv)) {
                            wlog('缺少动作');
                            return;
                        }
                        $res = lim_tcp($host . ':' . (APP_HW_PORT - 1), ['token' => APP::token(), 'action' => $action]);
                        wlog($res);
                        break;
                    case 'push':
                        if (!$mark = array_shift($argv)) {
                            $mark = date('m-d');
                        }
                        $script = " git add . && git commit -m '" . $mark . "' && git push;";
                        wlog(shell_exec($script));
                        wlog('推送代码成功');
                        break;
                    case 'create':
                        print_r($argv);
                        break;
                    case 'lim':
                        $script = "grep -q 'alias lim=\"php start.php\"' ~/.bashrc && echo '命令已存在' || ( sed -i '\$a alias lim=\"php start.php\"' ~/.bashrc && echo '添加快捷命令成功')  && source ~/.bashrc";
                        wlog(shell_exec($script));
                        break;
                    default:

                        echo 'hello';
                        break;
                }
            }
        }

        //运行函数
        if ($fn = $o['F'] ?? null) {
            try {
                Configer::init();
                // print_r(get_included_files());
                run(fn() => $fn(...$argv));
            } catch (\Swoole\ExitException $e) {
                print_r($e);
            }

        }

        //运行对象
        if ($obj = $o['O'] ?? null) {
            Configer::init();
            run(fn() => objRun($o['O'], ...$argv));
        }

        //操作服务
        if (isset($o['S'])) {

            switch ($o['S']) {
                case 'run':
                    Server::run(isset($o['d']) ? true : false);
                    break;
                case 'stop':
                case 'reload':
                    //清理缓存
                    run(function () use ($o) {
                        \swoole\timer::clearAll();
                        $pid = file_get_contents('/var/log/' . APP_NAME . '.pid');
                        $num = $o['S'] == 'stop' ? 15 : 10;
                        $n   = [15 => '停止', 10 => '重启'];
                        $ret = \co::exec('kill -' . $num . ' ' . $pid);
                        if ($ret['code'] === 0) {
                            wlog("服务" . $n[$num] . "成功");
                        }
                    });
                    break;
            }
        }
        // print_r(get_defined_constants(true)['user']);
        // print_r(get_included_files());

    }
}
