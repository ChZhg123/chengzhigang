<?php
namespace app\common\model;

use think\Model;

class GoodsSku extends Model
{
    //新增数据
    public function createData($data)
    {
        $data['create_time'] = date('Y-m-d H:i:s');
        $data['update_time'] = date('Y-m-d H:i:s');
        return $this->strict(false)->insertGetId($data);
    }
    public function getThumbAttr($value)
    {
        if(empty($value)){
            return '';
        }else{
            return (is_https()?'https://':'http://').Img_Url.'/'.$value;
        }
    }
    //更新数据
    public function updateData($where=[], $data=[])
    {
        $tableData = $this->get($where);
        foreach ($data as $k=>$v) {
            if (!isset($tableData[$k])) {
                unset($data[$k]);
            }
        }
        $data['update_time'] = date('Y-m-d H:i:s');
        return $this->where($where)->update($data);
    }
}
