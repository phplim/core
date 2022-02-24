<?php
declare (strict_types = 1);
namespace lim;

/**
 *
 */
class Apis
{

    public $data, $req;

    public function __construct($req)
    {
        $this->req  = $req;
        $this->data = $req->all;
    }

    public function auth()
    {
        \lim\Model::$data = $this->data;
        switch ($this->req->auth) {
            case 'public':
                break;
            case 'token':
                App::token($this->req->header['token'] ?? '', true);
                break;
            case 'user':
                $token               = App::token($this->req->header['token'] ?? '', true);
                list($time, $info)   = explode('|', $token);
                \lim\App::$ext->user = $user = (object) json_decode($info, true);
                wlog($user->uid . ' ' . $this->req->url, 'apis');
                break;
        }
        return $this;
    }

    public function before()
    {
        return $this;
    }

    public function __call($method, $args)
    {
        list($res, $message) = $this->__init($method);
        $res!==null ? suc($res, $message.'成功') : err($message.'失败') ;
    }

    public function __destruct()
    {

    }
}
