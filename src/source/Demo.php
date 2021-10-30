<?php
declare (strict_types = 1);
namespace app\api;

class Demo extends Api
{
    public function tt($value='')
    {
        suc([config('rules'),$this]);
    }
}
