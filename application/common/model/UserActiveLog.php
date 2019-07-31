<?php
namespace app\common\model;

use think\Model;

class UserActiveLog extends Model
{
    //新增数据
    public function createData($data)
    {
        return $this->allowField(true)->save($data);
    }
}
