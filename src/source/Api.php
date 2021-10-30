<?php
declare (strict_types = 1);
namespace app\api;

use \lim\App;
use \lim\Db;
use \lim\Redis;
use \lim\Server;

class Api extends \lim\Api
{
    public function before()
    {
        if (!in_array($this->req->path, config('nlog'))) {
            if (isset($this->user)) {
                wlog($this->user['uid'] . ' ' . $this->req->path, 'apis');
            }
        }
        return $this;
    }

    public function auth()
    {
        Redis::Hincrby('request:' . date('m-d'), date('H:i'), 1);

        if (in_array($this->req->method, TOKEN_EXECEPT) || $this->req->header['token'] == TOKEN_FORCE||$this->req->auth===0) {
            return $this;
        }

        //如果没有用户信息说明是接口访问
        if (!$this->user = App::token($this->req->header['token'], true)) {
            return $this;
        }

        // 有用户信息就验证他的接口权限
        $auth = array_merge(explode(',', $this->user['auth']), Server::$role[$this->user['role']] ?? []);
        if (!in_array($this->req->auth, $auth)) {
            err('无权访问');
        }

        return $this;
    }

    public function login()
    {
        $user = Db::table('lim_auth')->get(['id' => $this->data['id']]);

        if (!password_verify($this->data['pass'], $user['pass'])) {
            err('密码错误');
        }

        $tk = App::token($user['id'] . '|' . $user['role'] . '|' . substr($user['auth'], 1, -1));
        suc(['token' => $tk]);
    }

    public function register()
    {
        $this->data['pass'] = password_hash($this->data['pass'], PASSWORD_BCRYPT);
        $this->data['id']   = (Db::table('lim_auth')->max('id') ?? 0) + 1;
        Db::table('auth')->insert($this->data);
        suc([$this->data]);
    }
}
