<?php
namespace app\common\model;
use think\Model;
class SideNav extends Model
{
    protected $autoWriteTimestamp = 'datetime';
    public function getIconAttr($value){
        if(empty($value)){
            return "fa-circle-o";
        }else{
            return $value; 
        }
    }
    //新增数据
    public function createData($data){
        if(!isset($data['icon'])||empty($data['icon'])){
            $data['icon'] = "fa-circle-o";
        }
        return $this->allowField(true)->save($data);
    }
    //更新数据
    public function updateData($where=[],$data=[]){
        $tableData = $this->get($where);
        foreach($data as $k=>$v){
            if(!isset($tableData[$k])){
                unset($data[$k]);
            }
        }
        $data['update_time'] = date('Y-m-d H:i:s');
        return $this->where($where)->update($data);
    }
}