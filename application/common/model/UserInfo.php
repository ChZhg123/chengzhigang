<?php
namespace app\common\model;

use think\Model;

class UserInfo extends Model
{
    protected $autoWriteTimestamp = 'datetime';
    //新增数据
    public function createData($data)
    {
        return $this->allowField(true)->save($data);
    }
    public function getHeadPicAttr($value){
        if(empty($value)){
            return '/static/admin/custom/image/head.jpg';
        }else{
            return $value;
        }
    }
    //更新数据
    public function updateData($where=[], $data=[])
    {
        $data['update_time'] = date('Y-m-d H:i:s');
        return $this->where($where)->update($data);
    }
}
