<?php
namespace app\common\model;

use think\Model;

class ForumInfo extends Model
{
    protected $autoWriteTimestamp = 'datetime';
    //新增数据
    public function createData($data)
    {
        return $this->allowField(true)->save($data);
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

    // public function getCreateTimeAttr($date){
    //     $str = ''; 
    //     $timer = strtotime($date); 
    //     $diff = $_SERVER['REQUEST_TIME'] - $timer; 
    //     $day = floor($diff / 86400); 
    //     $free = $diff % 86400; 
    //     if($day > 0) { 
    //         return $day."天前"; 
    //     }else{ 
    //         if($free>0){ 
    //             $hour = floor($free / 3600); 
    //             $free = $free % 3600; 
    //                 if($hour>0){ 
    //                     return $hour."小时前"; 
    //                 }else{ 
    //                     if($free>0){ 
    //                         $min = floor($free / 60); 
    //                         $free = $free % 60; 
    //                         if($min>0){ 
    //                             return $min."分钟前"; 
    //                         }else{ 
    //                             if($free>0){ 
    //                                 return $free."秒前"; 
    //                             }else{ 
    //                                 return '刚刚'; 
    //                             } 
    //                        } 
    //                     }else{ 
    //                         return '刚刚'; 
    //                     } 
    //                } 
    //        }else{ 
    //            return '刚刚'; 
    //        } 
    //     } 
    // }

}
