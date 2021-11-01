<?php
declare (strict_types = 1);
namespace lim;

class App
{
    public static $cache = null, $config = [];

    /**
     * WebSocket握手
     * @Author   Wayren
     * @DateTime 2021-10-25T10:40:30+0800
     * @param    [type]                   $server  [description]
     * @param    [type]                   $request [description]
     * @return   [type]                            [description]
     */
    public function open($server, $request)
    {
        try {
            // App::speed($request->server['remote_addr'] . 'ws');
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
    public function message($server, $frame)
    {
        try {
            // \app\Hook::message($frame);
            if (substr($frame->data, 0, 1) != '{') {
                $frame->data = self::crypt($frame->data, true);
            }
            // wlog($frame->data);
            if (!$info = json_decode((string) $frame->data, true) ?? null) {
                self::push('非法请求');
            }

            list($path, $class, $method,$rule, $auth) = App::parseUri($info['action']);

            $req         = new \StdClass();
            $req->class  = $class;
            $req->method = $method;
            $req->auth   = $auth;
            $req->all    = $info['data'];
            $req->header = ['token' => $info['token']];
            $req->path   = $path;
            $req->rule                          = $rule;
            $req->fd   = $frame->fd;

            (new $class($req))->auth()->check()->before()->$method();

        } catch (\Swoole\ExitException $e) {

            $res = json_decode($e->getStatus(), true);

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
                    $server->push((int) $id, json_encode($res, 256));
                    // wlog($id);
                }
            }
        }
    }

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
        if ($v) {
            return Server::$cache->set($k, $v, $t);
        }

        return Server::$cache->get($k);
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

        list($path, $class, $method, $rule,$auth) = App::parseUri($_SERVER['REQUEST_URI']);
        $req                                = new \StdClass();
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
        $req->rule                          = $rule;
        $req->path         = $path;
        (new $class($req))->auth()->check()->before()->$method();
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

        try {
            // \app\Hook::request($request, $response);
            $get  = $request->get;
            $post = $request->post ?? json_decode($request->getContent(), true);
            $all  = array_merge($get ?? [], $post ?? []);

            list($path, $class, $method, $rule,$auth) = $a = App::parseUri($request->server['request_uri']);
            $req                                = new \StdClass();
            $req->header                        = $request->header;
            $req->header['ip']                  = $request->header['ip'] ?? $request->server['remote_addr'];
            $req->all                           = $all;
            $req->class                         = $class;
            $req->method                        = $method;
            $req->auth                          = $auth;
            $req->rule                          = $rule;
            $req->path                          = $path;
            $ret                                = (new $class($req))->auth()->check()->before()->$method();

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
     * 定时任务
     * @Author   Wayren
     * @DateTime 2021-10-08T16:23:58+0800
     * @param    [type]                   $server [description]
     * @param    [type]                   $task   [description]
     * @return   [type]                           [description]
     */
    public static function task($server, $task)
    {
        // \app\Hook::task($task);
        if (isset($task->data['run'])) {
            objRun($task->data['run']);
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

        if (str_starts_with($uri, '/configer') && APP_ENV=='dev') {
   
            $res = [$uri, '\\configer\\Configer', explode('/', $uri)[2] ?? 'index', 0,0];
            return $res;
        }

        $route = config('route');

        if (isset($route[$uri])) {
            return $route[$uri];
        }
        
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

            $tk = explode('|', $auth);

            if (time() - array_shift($tk) > TOKEN_EXP) {
                err('token过期', 301);
            }

            //api接口访问 非用户访问
            if (empty($tk)) {
                return null;
            }

            return array_combine(['uid', 'role', 'auth'], $tk);
        }

        return base64_encode(openssl_encrypt(time() . '|' . $v, TOKEN_ALGO, TOKEN_KEY, 1, TOKEN_IV));
    }

    public static function crypt($data = '', $de = false)
    {
        if ($de) {
            return openssl_decrypt(base64_decode($data), TOKEN_ALGO, TOKEN_KEY, 1, TOKEN_IV);
        }

        if (is_array($data)) {
            $data = json_encode($data);
        }

        return base64_encode(openssl_encrypt($data, TOKEN_ALGO, TOKEN_KEY, 1, TOKEN_IV));
    }
}
