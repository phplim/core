<?
declare (strict_types = 1);
namespace lim;

class DataHandle
{
    public function __construct(public $data)
    {
        
    }

    /**
     * 转化成KV数据 如果value 包含,则说明是多value
     * @Author   Wayren
     * @DateTime 2021-12-24T14:08:36+0800
     * @param    string                   $key   [description]
     * @param    string                   $value [description]
     * @return   [type]                          [description]
     */
    public function kv($key='',$value='')
    {
        if (!str_contains($value,',')) {
            return array_column($this->data,$value,$key);
        }

        $cols = explode(',',$value);
        foreach ($this->data as $k =>$v ) {
            foreach ($cols as $col) {
                if (!isset($v[$col])) {
                    continue;
                }
                $ret[$v[$key]][$col]=$v[$col];
            }
        }
        
        return $ret??[];
    }

    public function cols($cols='')
    {
        $cols = explode(',',$cols);
        $res = array_diff(array_keys(array_shift($this->data)),$cols);
        foreach ($this->data as $k =>$v ) {
            foreach ($res as $key) {
                unset($v[$key]);
            }
            $ret[] =$v;
        } 
        return $ret??null;
    }

    public function each($fn=null,&$e=null)
    {
        foreach ($this->data as $k => $v) {
            if ($fn) {
                $e[]=$fn($v);
            } 
        }
    }
}
