<?php
namespace app\admin\controller;

use think\facade\Session;

class Index extends Base
{
    /**
     * 后台首页
     * @method index
     * @author chengzhigang
     */
    public function index()
    {
        return $this->fetch();
    }

    /**
     * 后台登录
     * @method login
     * @author chengzhigang
     * @param username password captcha
     */
    public function login()
    {
        if (request()->isGet()) {
            if(!empty(session('admin_id'))){
                return redirect('index');
            }
            return $this->fetch();
        } else {
            try {
                $data = $this->param;
                //数据验证
                if (!isset($data['username']) || empty($data['username'])) {
                    apiReturn('请输入用户名');
                }
                if (!isset($data['password']) || empty($data['password'])) {
                    apiReturn('请输入密码');
                }
                if (!isset($data['captcha']) || empty($data['captcha'])) {
                    apiReturn('请输入验证码');
                }
                if (!captcha_check($data['captcha'])) {
                    //验证失败
                    apiReturn('验证码错误或失效');
                };
                $admininfo = $this->AdminUserModel->where('username', $data['username'])->find();
                if (empty($admininfo)) {
                    apiReturn('用户名不存在');
                } else {
                    if (encryptPwd($data['password']) != $admininfo['password']) {
                        apiReturn('密码不正确',1,encryptPwd($data['password']));
                    } else {
                        $navs = get_side_navs($admininfo['id']);
                        session('side_navs', $navs);
                        session('admin_id', $admininfo['id']);
                        session('admin_info', $admininfo);
                        //记住密码
                        if($data['remember']==1){
                            cookie('cookie_admin_id',encryptPwd($admininfo['id']),3600*24*30);
                        }
                        //记录登录日志
                        write_login_log($admininfo['id'], $admininfo['username']);
                        apiReturn('登录成功', 0, array('url' => url('index')));
                    }
                }
            } catch (\Exception $e) {
                write_error_log($e); //记录错误日志
                apiReturn(Error_Log);
            }
        }
    }

    /**
     * 退出登录
     * @method loginout
     * @author chengzhigang
     */
    public function loginout()
    {
        try {
            Session::clear();
            cookie('cookie_admin_id',null);
            return redirect('login');
        } catch (\Exception $e) {
            write_error_log($e); //记录错误日志
        }
    }
}
