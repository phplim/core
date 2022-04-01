<?
declare (strict_types = 1);
namespace lim;

/**
 * 数据处理类
 */
class DataV
{
    public $data = [];
    
    public function __construct(&$data = [])
    {
        $this->data = $data;
    }

    public function toArray($cols = '')
    {
        if (!$this->data) {
            return $this;
        }

        if (empty($cols)) {
            $this->data = json_decode($this->data, true);
        } else {

            if ($cols=='*') {
                $col = array_keys($this->data);
            } else{
                $col = explode(',', $cols);
            }

            foreach ($this->data as $k => $v) {
                if (in_array($k, $col)) {
                    $this->data[$k] = json_decode($v, true);
                }
            }
        }
        return $this;
    }

    public function toArrays($cols = '*')
    {
        $cols = explode(',', $cols);
        foreach ($this->data as $k => $v) {
            foreach ($v as $col => $value) {
                if (in_array($col, $cols)) {
                    $v[$col] = json_decode((string)$value, true);
                }
            }
            $this->data[$k] = $v;
        }
        
        return $this;
    }


    /**
     * 将二维数组转化为Key-value键值对
     * @Author   Wayren
     * @DateTime 2021-11-23T17:56:43+0800
     * @param    string                   $key  [description]
     * @param    [type]                   $cols [description]
     * @return   [type]                         [description]
     */
    public function kv($key='',$cols=null)
    {
        $cols = $cols==null ? [] : explode(',', $cols);
        foreach ($this->data as $k => $v) {
            if (empty($cols)) {
                $kv[$v[$key]]=$v;
            } else {
                foreach ($cols as $col) {
                    if (isset($v[$col])) {
                        $kv[$v[$key]][$col]=$v[$col];
                    }
                }
            }

            //如果只存在一个数据项则将数组转提升为对应的数据
            if (count($kv[$v[$key]])==1) {
                $kv[$v[$key]] = end($kv[$v[$key]]);
            }
        }
        return $kv;
    }

    public function __destruct()
    {

    }
}
