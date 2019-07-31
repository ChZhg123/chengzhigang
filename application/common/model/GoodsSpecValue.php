<?php
namespace app\common\model;

use think\Model;

class GoodsSpecValue extends Model
{
    protected $autoWriteTimestamp = 'datetime';
    //新增数据
    public function createData($data)
    {
        $data['create_time'] = date('Y-m-d H:i:s');
        $data['update_time'] = date('Y-m-d H:i:s');
        return $this->strict(false)->insertGetId($data);
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
