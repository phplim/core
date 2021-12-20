<?
declare (strict_types = 1);
namespace lim;

class DataHandle
{
    public function __construct(public $res)
    {

    }

    public function each($fn=null,&$e=null)
    {
        foreach ($this->res as $k => $v) {
            if ($fn) {
                $e[]=$fn($v);
            } 
        }
    }
}
