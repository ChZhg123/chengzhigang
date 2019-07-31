<?php
namespace app\common\model;

use think\Model;

class LeaveInfo extends Model
{
    //新增数据
    public function createData($data)
    {
        return $this->allowField(true)->save($data);
    }

    public function getImageAttr($value)
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
