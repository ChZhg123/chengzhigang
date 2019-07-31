<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2016 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: 流年 <liu21st@gmail.com>
// +----------------------------------------------------------------------

// 应用公共文件
/**
 * 验证手机号格式
 * @method 
 * @author chengzhigang
 * @param $phone
 * @return 
 */
function check_phone($phone) {
	$search = '/^0?1[3|4|5|6|7|8][0-9]\d{8}$/';
	if (preg_match($search, $phone)) {
		return true;
	} else {
		return false;
	}
}
//邮箱验证
function check_email($email){
	$search='/^[a-zA-Z0-9]+([-_.][a-zA-Z0-9]+)*@([a-zA-Z0-9]+[-.])+([a-z]{2,5})$/ims';
	if(preg_match($search,$email)){
		return true;
	}else{
		return false;
	}
}
//检测登录账号格式（英文、数字、下划线4-20位字符）(?!_)(?!.*?_$)(?!\d+$)
function check_username($username) {
	$search = '/^[A-Za-z0-9]{4,20}$/u';
	if (preg_match($search, $username)) {
		return true;
	} else {
		return false;
	}
}
/**
 * 检测登录密码格式（英文、数字、下划线6-20位字符）(?!_)(?!.*?_$)(?!\d+$)
 * @method check_password
 * @author chengzhigang
 * @param $password
 * @return 
 */
function check_password($password) {
	$search = '/^[0-9A-Za-z_]{6,20}$/u';
	if (preg_match($search, $password)) {
		return true;
	} else {
		return false;
	}
}

/**
 * 完善link
 * @method perfect_link
 * @author chengzhigang<1256699215@qq.com>
 * @param $link
 * @return $link
 */
function perfect_link($link){
	if(!empty($link)){
		if(strtolower(substr($link,0,7)) == "http://" || strtolower(substr($link,0,8)) == "https://"){
			$link = $link;
		}elseif(strtolower(substr($link,0,5)) == "http:"){
			$link = "http://".substr($link,5);
		}elseif(strtolower(substr($link,0,6)) == "https:"){
			$link = "https://".substr($link,6);
		}else{
			$link = "http://".$link;
		}
	}
	return $link;
}

/**
 * 判断 http / https 协议
 * @return bool true是https  false是http
 */
function is_https(){
    if (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off') {
        return true;
    } elseif (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
        return true;
    } elseif (!empty($_SERVER['HTTP_FRONT_END_HTTPS']) && strtolower($_SERVER['HTTP_FRONT_END_HTTPS']) !== 'off') {
        return true;
    }
    return false;
}