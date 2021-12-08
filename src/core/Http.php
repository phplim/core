<?php
declare (strict_types = 1);
namespace lim;

use function Swoole\Coroutine\Http\get;
use function Swoole\Coroutine\Http\post;

class Http
{
    public static function __callStatic($method, $args)
    {
        try {
            return call_user_func_array([new HttpHandle(), $method], $args);
        } catch (Throwable $e) {
            print_r($e);
        }
    }
}

/**
 * 
 */
class HttpHandle 
{
    public $data=null;
    
    function __construct()
    {
        // code...
    }

    public function get($value = '')
    {
        $data = get($value);
        $this->data = $res->getBody();
        return $this;
    }

    public function post($url = '', $data = [], $header = [])
    {
        $res = post($url, $data, [], $header);
        $this->data = $res->getBody();
        return $this;
    }

    public function json()
    {
        if(!$this->data){
            return null;
        }
        return json_decode($this->data,true);
    }

}