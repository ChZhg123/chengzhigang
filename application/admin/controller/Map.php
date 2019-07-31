<?php
/**
 * 地图管理
 * @author chengzhigang<1256699215@qq.com>
 */
namespace app\admin\controller;
use think\facade\Env;
class Map extends Base
{
    public function world()
    {
        return $this -> fetch();
    }
    public function china(){
        return $this -> fetch();
    }
}