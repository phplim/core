<?php
declare (strict_types = 1);

namespace lim;
use function Swoole\Coroutine\run;


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

        if (empty($o)&&!empty($argv)) {
            $act = array_shift($argv);
            switch ($act) {
                case 'clear':
                    
                    break;
                
                default:
                    // code...
                    break;
            }
        }

        //运行函数
        if ($fn = $o['F'] ?? null) {
            try {
                run(fn() => $fn(...$argv));
            } catch (\Swoole\ExitException  $e) {
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
                    if(isset($o['d'])){
                        Server::run(true);
                    } else {
                        Server::run();
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
                        $pid = file_get_contents( '/var/log/'.config('server.name').'.pid');
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
