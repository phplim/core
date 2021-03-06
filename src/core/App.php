<?php
declare (strict_types = 1);
namespace lim;

use \swoole\Timer;

class App
{
    public static $cache = null, $config = [], $request = null, $ext = null;

    private $req = null;
    /**
     * WebSocket握手
     * @Author   Wayren
     * @DateTime 2021-10-25T10:40:30+0800
     * @param    [type]                   $server  [description]
     * @param    [type]                   $request [description]
     * @return   [type]                            [description]
     */

    public static function open($server, $request)
    {
        try {
            static::$request = $request;

            //socket.io
            if (isset($request->get['transport'])) {
                $data = [
                    'sid'          => time(),
                    'upgrades'     => [],
                    'pingInterval' => 25000,
                    'pingTimeout'  => 20000,
                ];
                $server->push((int) $request->fd, '0' . json_encode($data)); //socket is open
                Timer::tick(25 * 1000, fn() => $server->push((int) $request->fd, 2));
            } else {
                $server->push((int) $request->fd, '{"action":"onOpen"}');
            }

        } catch (\Swoole\ExitException $e) {
            $server->push((int) $request->fd, $e->getStatus());
            $server->disconnect($request->fd);
        }
    }

    /**
     * WebSocket消息
     * @Author   Wayren
     * @DateTime 2021-10-23T17:25:49+0800
     * @param    [type]                   $server [description]
     * @param    [type]                   $frame  [description]
     * @return   [type]                           [description]
     */
    public static function message($server, $frame)
    {
        try {

            if ($frame->data == 'ping') {
                return;
            }

            //大括号的位置大于0小于3的时候 说明是 socket.io 请求的具体数据
            $index = strpos($frame->data, '[');
            if ($index <= 2 && $index > 0) {
                $code = substr($frame->data, 0, $index);
                $data = json_decode(substr($frame->data, $index), true);
            } else {
                $code = $frame->data;
                $data = '';
            }

            $req           = new \StdClass();
            $req->header   = static::$request->header ?? [];
            $req->socketio = 1;
            switch ($code) {
                case 0:break;
                case 2:break;
                case 3:return;
                case 40:$server->push((int) $frame->fd, '40{"sid":' . time() . '}');
                    return;
                case 42:
                    $info = array_shift($data);

                    if (!$url = $info['action'] ?? null) {
                        err('action必填');
                    }

                    $req->header['token'] = $info['token'] ?? '';
                    $req->all             = $info['data'] ?? [];
                    $req->receive         = $info['receive'] ?? null;

                    list($path, $class, $method, $rule, $auth, $name) = App::parseUri($url);

                    $req->receive??=$method;
                    $req->class  = $class;
                    $req->method = $method;
                    $req->name   = $name;
                    $req->auth   = $auth;
                    $req->path   = $path;
                    $req->rule   = $rule;
                    $req->fd     = $frame->fd;
                    break;
                default:
                    // $req->socketio = 0;

                    $info = json_decode($code, true) ?? null;

                    if (!$info) {
                        err('非法请求');
                        return;
                    }

                    $req           = (object) Helper::parseRequest($info['action']);
                    $req->socketio = 0;
                    $req->all      = $info['data'];
                    $req->data     = $info['data'];
                    $method        = $req->method;
                    $req->receive??=$method;
                    $req->header['token'] = $info['token'] ?? '';
                    $req->fd              = $frame->fd;
                    $class                = $req->class;

                    break;
            }
            self::$ext = $req;
            (new $class($req))->auth()->check()->before()->$method();

        } catch (\Swoole\ExitException $e) {

            $data = json_decode($e->getStatus(), true);

            if ($req->socketio) {
                if ((int) $data['code'] != 1) {
                    $server->push((int) $frame->fd, '42' . json_encode([$req->receive ?? 'error', $data], 256));
                    return;
                }
                $res = '42' . json_encode([$req->receive, $data], 256);
            } else {
                $res['action'] = $req->method ?? null;
                $res           = json_encode(array_merge($res, $data), 256);
            }

            if (!isset($res['uid'])) {
                $uids = $frame->fd;
            } else {
                $uids = $res['uid'];
                unset($res['uid']);
            }

            if (!is_array($uids)) {
                $uids = [$uids];
            }

            foreach ($uids as $id) {
                if ($server->isEstablished($id)) {
                    $server->push((int) $id, $res);
                }
            }
        }
    }

    /**
     * [push description]
     * @Author   Wayren
     * @DateTime 2021-12-13T11:20:09+0800
     * @param    array                    $data [description]
     * @param    [type]                   $uid  [description]
     * @return   [type]                         [description]
     */
    public static function push($data = [], $uid = null)
    {
        if ($uid) {
            $data = array_merge($data, ['uid' => $uid]);
        }
        exit(json_encode($data, 256));
    }

    /**
     * 请求频率
     * @Author   Wayren
     * @DateTime 2021-10-11T14:19:31+0800
     * @param    string                   $ip    [description]
     * @param    integer                  $speed [description]
     * @param    integer                  $time  [description]
     * @return   [type]                          [description]
     */
    public static function speed($ip = '', $speed = SPEED, $time = 60)
    {
        if (!$num = self::cache($ip)) {
            self::cache($ip, 1, $time);
            return 1;
        }

        $num++;

        if ($num > $speed) {
            err('请求频率过高');
        }

        self::cache($ip, $num, $time);
        return $num;
    }

    /**
     * 缓存方法
     * @Author   Wayren
     * @DateTime 2021-10-08T15:16:52+0800
     * @param    [string]                   $k [description]
     * @param    [all]                   $v [description]
     * @param    integer                  $t [description]
     * @return   [type]                      [description]
     */
    public static function cache($k, $v = null, $t = 0)
    {
        if (!Server::$cache) {
            Server::$cache = new \Yac();
        }
        if ($v) {
            return Server::$cache->set($k, $v, $t);
        }

        return Server::$cache->get($k);
    }

    /**
     * Swoole请求
     * @Author   Wayren
     * @DateTime 2021-10-08T15:16:37+0800
     * @param    [type]                   $request  [description]
     * @param    [type]                   $response [description]
     * @return   [type]                             [description]
     */
    public static function request($request, $response)
    {

        if ($request->server['path_info'] == '/favicon.ico' || $request->server['request_uri'] == '/favicon.ico') {
            $response->end();
            return;
        }

        $response->header("Access-Control-Allow-Origin", "*");
        $response->header("Access-Control-Allow-Methods", "*");
        $response->header("Access-Control-Allow-Headers", "*");

        try {
            $req         = (object) Helper::parseRequest($request->server['request_uri']);
            $class       = $req->class;
            $method      = $req->method;
            $req->header = $request->header;
            $req->files  = $request->files;
            $req->all    = array_merge($request->get ?? [], $request->post ?? []);
            $json        = [];
            if ($tmp = $request->getContent()) {
                if (DATA_CRYPT) {
                    $json = self::crypt($tmp, true);
                }
                if (substr($tmp, 0, 1) == '{') {
                    $json = json_decode($tmp, true);
                }
            }
            $req->all  = array_merge($req->all, $json ?? []);
            self::$ext = $req;
            $ret       = (new $class($req))->auth()->before()->$method();
            $response->end($ret);

        } catch (\Swoole\ExitException $e) {

            $ret = $e->getStatus();
            if (substr((string) $ret, 0, 1) == '{') {
                $response->header("Content-Type", 'application/json');
            } else {
                $response->header("Content-Type", 'text/html');
            }
            $response->end($ret);
        }
    }

    /**
     * 解析URL
     * @Author   Wayren
     * @DateTime 2021-09-29T17:05:57+0800
     * @param    [type]                   $uri [description]
     * @return   [type]                        [description]
     */
    public static function parseUri($uri)
    {
        $uri = explode('?', strtolower($uri))[0];

        if (str_starts_with($uri, '/configer') && APP_ENV == 'dev') {

            $res = [$uri, '\\configer\\Configer', explode('/', $uri)[2] ?? 'index', 0, 0, ''];
            return $res;
        }

        $route = config('route');

        if (isset($route[$uri])) {
            return $route[$uri];
        }
        // print_r([$route, $uri]);
        err('非法请求');

    }

    /**
     * [token 加密解密]
     * @Author   Wayren
     * @DateTime 2021-10-12T14:42:54+0800
     * @param    string                   $v  [description]
     * @param    boolean                  $de [如果为真就是解密,为假就是加密]
     * @return   [type]                       [description]
     */
    public static function token($v = '', $de = false)
    {
        if ($de) {
            if (!$v) {
                err('token必填', 300);
            }

            if (!$auth = openssl_decrypt(base64_decode($v), TOKEN_ALGO, TOKEN_KEY, 1, TOKEN_IV)) {
                err('token非法', 301);
            }

            return $auth;
            // $tk = explode('|', $auth);

            //API接口访问 只有一个时间值
            // if (count($tk)==1) {
            //     return null;
            // }

            // if (time() - array_shift($tk) > TOKEN_EXP) {
            //     err('token过期', 301);
            // }

            //api接口访问 非用户访问

            // if(count($tk)==4){
            //     return array_combine(['crt', 'uid', 'role', 'auth'], $tk);
            // }

            // return null;
        }

        return base64_encode(openssl_encrypt(time() . '|' . (is_array($v) ? json_encode($v, 256) : $v), TOKEN_ALGO, TOKEN_KEY, 1, TOKEN_IV));
    }

    public static function crypt($data = '', $de = false)
    {
        if ($de) {
            if (!$ret = openssl_decrypt(base64_decode($data), TOKEN_ALGO, TOKEN_KEY, 1, TOKEN_IV)) {
                err('密文错误');
            }

            if (substr($ret, 0, 1) == '{') {
                $ret = json_decode($ret, true);

            }

            return $ret;
        }

        if (is_array($data)) {
            $data = json_encode($data);
        }

        return base64_encode(openssl_encrypt((string) $data, TOKEN_ALGO, TOKEN_KEY, 1, TOKEN_IV));
    }

    /**
     * 传统FPM请求
     * @Author   Wayren
     * @DateTime 2021-10-08T15:16:26+0800
     * @param    string                   $value [description]
     * @return   [type]                          [description]
     */
    public static function nginx($value = '')
    {
        // \app\Hook::nginx($frame);
        header('content-type:application/json');
        if (substr($_GET['s'], 0, 1) == '/') {unset($_GET['s']);}
        $get  = $_GET;
        $post = !empty($_POST) ? $_POST : json_decode(file_get_contents("php://input"), true);
        $all  = array_merge($get, $post ?? []);

        list($path, $class, $method, $rule, $auth) = App::parseUri($_SERVER['REQUEST_URI']);
        $req                                       = new \StdClass();
        foreach ($_SERVER as $k => $v) {
            $k = strtolower($k);
            if (str_contains($k, 'http')) {
                $req->header[substr($k, 5)] = $v;
            }
        }
        $req->header['ip'] = $_SERVER['HTTP_IP'] ?? $_SERVER['REMOTE_ADDR'];
        $req->all          = $all;
        $req->class        = $class;
        $req->method       = $method;
        $req->auth         = $auth;
        $req->rule         = $rule;
        $req->path         = $path;
        (new $class($req))->auth()->check()->before()->$method();
    }

}
