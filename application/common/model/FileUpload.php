<?php
namespace app\common\model;

use think\Model;

class FileUpload extends Model
{
    //新增数据
    public function createData($data)
    {
        $data['create_time'] = date('Y-m-d H:i:s');
        return $this->allowField(true)->save($data);
    }
}
