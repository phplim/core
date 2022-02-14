<?php
declare (strict_types = 1);
namespace configer;

use \lim\Db;

class Configer
{
    public $req, $vars = [];

    public function __construct($req)
    {
        $this->req = $req;
    }

    public function html($html)
    {
        $f =  'html/' . $html . '.html';
        extract($this->vars, EXTR_OVERWRITE);
        ob_start();
        if (PHP_SAPI !== 'cli') {
            header('content-type:text/html');
        }
        include $f;
        exit(ob_get_clean());
    }

    public function __call($method, $args)
    {
        if (in_array($method, ['auth', 'check', 'before'])) {
            return $this;
        }

        $this->html(explode('.', $method)[0]);
    }

    public function login()
    {
        if ($this->req->all['user'] != CONFIG_USER) {
            err('用户非法');
        }

        if ($this->req->all['pass'] != CONFIG_PASS) {
            err('密码错误');
        }

        suc(['token' => App::token($this->req->all['user'])]);
    }

    public function roleData()
    {
        return Db::table('lim_api')->select();
    }

    public function role()
    {
        switch ($this->req->header['action']) {
            case 'update':
                Db::use('config')->table('lim_role')->update($this->req->all);
                suc([]);
                break;
            case 'select':
                suc(Db::use('config')->table('lim_role')->select());
                break;
            default:
                // code...
                break;
        }

    }

    public function user()
    {
        // code...
    }

    /**
     * 生成配置缓存
     * 包括路由、权限
     * @Author   Wayren
     * @DateTime 2021-10-14T16:17:35+0800
     * @param    string                   $value [description]
     * @return   [type]                          [description]
     */
    public function mark()
    {
        $ret = Db::table('api')->select(['ORDER' => ['top' => 'ASC', 'mid' => 'ASC']]);
    }

    public function api()
    {
        switch ($this->req->header['action'] ?? null) {
            case 'role':
                $ret = Db::use('config')->table('lim_api')->select(['ORDER' => ['top' => 'ASC', 'mid' => 'ASC'], 'status' => 1]);
                foreach ($ret as $k => $v) {
                    if ($v['top'] == '0') {
                        $res[$v['mid']] = ['name' => $v['name'], 'sub' => []];
                        continue;
                    }
                    $res[$v['top']]['sub'][] = ['id' => $v['top'] . '.' . $v['mid'], 'name' => $v['name']];
                }
                suc($res);
                break;
            case 'class':
                suc(Db::use('config')->table('lim_api')->cols('id,class,name,mid,url,status')->select(['top' => '0']));
                break;
            case 'method':
                $ret = Db::use('config')->table('lim_api')->select(['ORDER' => ['top' => 'ASC', 'mid' => 'ASC']]);
                foreach ($ret as $k => $v) {
                    if ($v['top'] == '0') {
                        $tmp[$v['mid']] = $v['name'];
                        continue;
                    }
                    $res[] = array_merge($v, ['className' => $tmp[$v['top']]]);
                }
                suc($res);
                break;
            case 'delete':
                Db::use('config')->table('lim_api')->delete($this->req->all);
                $this->sync();
                suc([], '删除成功');
                break;
            case 'update':
                if (isset($this->req->all['sync'])) {
                    unset($this->req->all['sync']);
                    $top = Db::use('config')->table('lim_api')->cols('mid')->get(['id' => $this->req->all['id']]);
                    Db::use('config')->exec("UPDATE lim_api SET top = {$this->req->all['mid']} WHERE top = {$top}");
                }
                Db::use('config')->table('lim_api')->update($this->req->all);
                $this->sync();
                suc([], '更新成功');
                break;
            case 'insert':
                Db::use('config')->table('lim_api')->insert($this->req->all);
                $this->sync();
                suc([], '插入成功');
                break;
        }

        $this->html('api');
    }

    public function sync()
    {
        if (is_file('/tmp/'.APP_NAME.'_app.db')) {
            copy('/tmp/'.APP_NAME.'_app.db', APP.'config/app.db');
        }

        \lim\Server::reload();
    }
}
