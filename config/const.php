<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2018 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------

// +----------------------------------------------------------------------
// | Trace设置 开启 app_trace 后 有效
// +----------------------------------------------------------------------
use \think\facade\Request;
if(!defined('Author')){
    define('Author','chengzhigang');    //作者：chengzhigang
}

if(!defined('NickName')){
    define('NickName','观海听潮');    //作者：chengzhigang
}

if(!defined('Sign_Key')){
    define('Sign_Key','1256699215@qq.com');    //签名key：1256699215@qq.com
}

if(!defined('Error_Log')){
    define('Error_Log','服务器开小差啦');    //错误信息
}

if(!defined('Error_404')){
    define('Error_404','您访问的页面不存在');    //404信息
}

if(!defined('Sign_Time')){
    define('Sign_Time',60);    //签名时间戳有效期 默认1分钟
}

if(!defined('Code_Time')){
    define('Code_Time',60);    //验证码时间有效期 默认1分钟
}

if(!defined('Img_Url')){
    define('Img_Url','qiniu.chengzhigang.cn');    //图片域名
}

if(!defined('Page_Num')){
    define('Page_Num',10);    //列表页每页10条数据
}