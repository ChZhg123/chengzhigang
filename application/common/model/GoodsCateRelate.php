<?php
namespace app\common\model;

use think\Model;

class GoodsCateRelate extends Model
{
    //新增数据
    public function createData($data)
    {
        return $this->strict(false)->insertGetId($data);
    }
}
