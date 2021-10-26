<?php
declare (strict_types = 1);
namespace lim;

use function Swoole\Coroutine\Http\get;

class Http
{


    public static function get($value = '')
    {
        $data = get($value);
        return $data->getBody();
    }
}
