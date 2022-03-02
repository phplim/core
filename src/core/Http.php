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
    public $data = null;

    public function __construct()
    {
        // code...
    }

    public function get($value = '',array $options = null, array $headers = null, array $cookies = null)
    {
        $res       = get($value,$options,$headers,$cookies);
        print_r($res);
        $this->data = $res->getBody();
        return $this;
    }

    public function post($url = '', $data = [], $header = [],array $cookies = null)
    {
        $res        = post($url, $data, [], $header,$cookies);
         print_r($res);
        $this->data = $res->getBody();
        return $this;
    }

    public function curlPost($url, $post_data = array(), $header = "")
    {
        $header = empty($header) ? '' : $header;
        $ch     = curl_init(); // 启动一个CURL会话
        curl_setopt($ch, CURLOPT_URL, $url); // 要访问的地址
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // 对认证证书来源的检查   // https请求 不验证证书和hosts
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false); // 从证书中检查SSL加密算法是否存在
        curl_setopt($ch, CURLOPT_POST, true); // 发送一个常规的Post请求
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data); // Post提交的数据包
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5); // 设置超时限制防止死循环
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // 获取的信息以文件流的形式返回
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header); //模拟的header头
        $result = curl_exec($ch);
        curl_close($ch);
        $this->data = $result;
        return $this;
    }

    public function json()
    {
        if (!$this->data) {
            return null;
        }
        return json_decode($this->data, true);
    }

}
