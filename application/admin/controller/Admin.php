<?php
/**
 * 系统管理
 * @author chengzhigang<1256699215@qq.com>
 */
namespace app\admin\controller;

class Admin extends Base
{

    /**
     * 模块/菜单列表渲染
     * @method module
     * @author chengzhigang<1256699215@qq.com>
     */
    public function modulelist()
    {
        //列表路径存cookie
        Cookie('Admin/modulelist', request()->url());
        $filter = input('filter/s');
        $where = [];
        $where[] = array('level','eq',1);
        if (!empty($filter)) {
            $where[] = array('name', 'like', '%' . trim($filter) . '%');
        }
        $list = $this->SideNavModel->where($where)->field('id,name,icon,href,level,sort,create_time')->order('sort asc')->select();
        foreach ($list as &$val) {
            $val['child'] = $this->SideNavModel->where('pid', $val['id'])->field('id,name,icon,href,level,sort,create_time')->order('sort asc')->select();
            foreach ($val['child'] as &$vv) {
                $vv['child'] = $this->SideNavModel->where('pid', $vv['id'])->field('id,name,icon,href,level,sort,create_time')->order('sort asc')->select();
            }
        }
        $this->assign('list', $list);
        $this->assign('filter', $filter);
        return $this->fetch();
    }

    /**
     * 新增模块/菜单页面渲染
     * @method addModule
     * @author chengzhigang<1256699215@qq.com>
     */
    public function addModule()
    {
        if (request()->isGet()) {
            $count = $this->SideNavModel->count();
            //上级模块
            $firstModule = $this->SideNavModel->where('level', 1)->field('id,name')->order('sort')->select();
            $this->assign('firstModule', $firstModule);
            $this->assign('count', $count + 1);
            return $this->fetch();
        } else {
            try {
                $data = input('post.');
                if (empty($data['name'])) {
                    apiReturn('模块/菜单名称不能为空');
                }
                if (empty($data['href'])) {
                    apiReturn('模块/菜单路径不能为空');
                }
                if (!empty($data['secondid'])) {
                    $data['pid'] = $data['secondid'];
                    $data['level'] = 3;
                } elseif (!empty($data['firstid']) && empty($data['secondid'])) {
                    $data['pid'] = $data['firstid'];
                    $data['level'] = 2;
                } else {
                    $data['pid'] = 0;
                    $data['level'] = 1;
                }
                $res = $this->SideNavModel->createData($data);
                if ($res) {
                    //新增成功
                    apiReturn('新增成功', 0, array('url' => Cookie('Admin/modulelist')));
                } else {
                    apiReturn('新增失败');
                }
            } catch (\Exception $e) {
                write_error_log($e); //记录错误日志
                apiReturn(Error_Log);
            }
        }
    }

    /**
     * 编辑模块/菜单页面渲染
     * @method editModule
     * @author chengzhigang<1256699215@qq.com>
     */
    public function editModule()
    {
        if (request()->isGet()) {
            $id = input('id');
            //上级模块
            $firstModule = $this->SideNavModel->where('level', 1)->field('id,name')->order('sort')->select();
            $info = $this->SideNavModel->where('id', $id)->field('id,name,pid,href,icon,level,sort')->find();
            if ($info['level'] == 1) {
                $info['firstid'] = $info['pid'];
                $info['secondid'] = 0;
            } elseif ($info['level'] == 2) {
                $info['firstid'] = $info['pid'];
                $info['secondid'] = 0;
            } else {
                $parentid = $this->SideNavModel->where('id', $info['pid'])->value('pid');
                $info['firstid'] = $parentid;
                $info['secondid'] = $info['pid'];
            }
            $this->assign('firstModule', $firstModule);
            $this->assign('info', $info);
            return $this->fetch();
        } else {
            try {
                $data = input('post.');
                if (empty($data['name'])) {
                    apiReturn('模块/菜单名称不能为空');
                }
                if (empty($data['href'])) {
                    apiReturn('模块/菜单路径不能为空');
                }
                if (!empty($data['secondid'])) {
                    $data['pid'] = $data['secondid'];
                    $data['level'] = 3;
                } elseif (!empty($data['firstid']) && empty($data['secondid'])) {
                    $data['pid'] = $data['firstid'];
                    $data['level'] = 2;
                } else {
                    $data['pid'] = 0;
                    $data['level'] = 1;
                }
                $res = $this->SideNavModel->updateData(array('id' => $data['id']), $data);
                if ($res) {
                    //新增成功
                    apiReturn('编辑成功', 0, array('url' => Cookie('Admin/modulelist')));
                } else {
                    apiReturn('编辑失败');
                }
            } catch (\Exception $e) {
                write_error_log($e); //记录错误日志
                apiReturn(Error_Log);
            }
        }
    }

    /**
     * 删除模块/菜单
     * @method delModule
     * @author chengzhigang<1256699215@qq.com>
     */
    public function delModule()
    {
        try {
            $id = input('id/d');
            $child = $this->SideNavModel->where('pid', $id)->field('id,name')->select();
            if (count($child) > 0) {
                apiReturn('请先删除下级模块');
            }
            $res = $this->SideNavModel->where('id', $id)->delete();
            if ($res) {
                //解绑角色的权限
                $rolelist = $this->AdminRoleModel->column('navs');
                foreach ($rolelist as $val) {
                    $navarr = explode(',', $val);
                    foreach ($navarr as $k => $v) {
                        if ($v == $id) {
                            unset($navarr[$k]);
                        }
                    }
                    $this->AdminRoleModel->updateData(array('id' => $val), array('navs' => implode(',', $navarr)));
                }
                apiReturn('删除成功', 0, array('url' => Cookie('Admin/modulelist')));
            } else {
                apiReturn('删除失败');
            }
        } catch (\Exception $e) {
            write_error_log($e); //记录错误日志
            apiReturn(Error_Log);
        }
    }

    /**
     * 获取二级模块列表
     * @method getSecendModule
     * @author chengzhigang<1256699215@qq.com>
     * @param $pid 上级模块id
     * @return [ id=>模块id name=>模块名称 ]
     */
    public function getSecendModule()
    {
        try {
            $pid = input('pid/d');
            if (empty($pid)) {
                apiReturn('参数错误');
            }
            $where = [];
            $where['pid'] = $pid;
            $list = $this->SideNavModel->where($where)->field('id,name')->order('sort asc')->select();
            apiReturn('请求成功', 0, $list);
        } catch (\Exception $e) {
            write_error_log($e); //记录错误日志
            apiReturn(Error_Log);
        }
    }

    /**
     * 选择图标页面
     * @method iconlist
     * @author chengzhigang<1256699215@qq.com>
     * @param filter
     * @return 
     */
    public function iconlist()
    {
        $filter = input('filter/s');
        $where = [];
        if (!empty($filter)) {
            $where[] = array('name|desc', 'like', '%' . trim($filter) . '%');
        }
        $list = $this->FontAwesomeModel->where($where)->field('id,name,desc')->select();
        $this->assign('list', $list);
        $this->assign('filter', $filter);
        return $this->fetch();
    }

    /**
     * 角色列表渲染
     * @method rolelist
     * @author chengzhigang<1256699215@qq.com>
     */
    public function rolelist()
    {
        //列表路径存cookie
        Cookie('Admin/rolelist', request()->url());
        $filter = input('filter/s');
        $where = [];
        if (!empty($filter)) {
            $where[] = array('name', 'like', '%' . trim($filter) . '%');
        }
        $list = $this->AdminRoleModel->where($where)->field('id,name,desc,create_time')->order('create_time desc')->paginate(Page_Num);
        $this->assign('list', $list);
        $this->assign('filter', $filter);
        return $this->fetch();
    }

    /**
     * 新增角色页面渲染
     * @method addRole
     * @author chengzhigang<1256699215@qq.com>
     */
    public function addRole()
    {
        if (request()->isGet()) {
            $treelist = getTreeList();
            $this->assign('treelist', $treelist);
            return $this->fetch();
        } else {
            try {
                $data = input('post.');
                if (empty($data['name'])) {
                    apiReturn('角色名称不能为空');
                }
                if(isset($data['navs'])){
                    $data['navs'] = implode(',', array_unique($data['navs']));
                }
                $res = $this->AdminRoleModel->createData($data);
                if ($res) {
                    //新增成功
                    apiReturn('新增成功', 0, array('url' => Cookie('Admin/rolelist')));
                } else {
                    apiReturn('新增失败');
                }
            } catch (\Exception $e) {
                write_error_log($e); //记录错误日志
                apiReturn(Error_Log);
            }
        }
    }

    /**
     * 编辑角色页面渲染
     * @method addRole
     * @author chengzhigang<1256699215@qq.com>
     */
    public function editRole()
    {
        if (request()->isGet()) {
            $id = input('id');
            $treelist = getTreeList($id);
            $info = $this->AdminRoleModel->where('id', $id)->field('id,name,desc')->find();
            $this->assign('info', $info);
            $this->assign('treelist', $treelist);
            return $this->fetch();
        } else {
            try {
                $data = input('post.');
                if (empty($data['name'])) {
                    apiReturn('角色名称不能为空');
                }
                if(isset($data['navs'])){
                    $data['navs'] = implode(',', array_unique($data['navs']));
                }
                $res = $this->AdminRoleModel->updateData(array('id' => $data['id']), $data);
                if ($res) {
                    //编辑成功
                    apiReturn('编辑成功', 0, array('url' => Cookie('Admin/rolelist')));
                } else {
                    apiReturn('编辑失败');
                }
            } catch (\Exception $e) {
                write_error_log($e); //记录错误日志
                apiReturn(Error_Log);
            }
        }
    }

    /**
     * 删除角色
     * @method delRole
     * @author chengzhigang<1256699215@qq.com>
     * @param 
     * @return 
     */
    public function delRole()
    {
        try {
            $id = input('id/d');
            //先删除该角色下的管理员
            $adminlist = $this->AdminUserModel->where('role_id', $id)->select();
            if (count($adminlist) > 0) {
                apiReturn('请先删除该角色下的管理员');
            }
            $res = $this->AdminRoleModel->where('id', $id)->delete();
            if ($res) {
                apiReturn('删除成功', 0, array('url' => Cookie('Admin/rolelist')));
            } else {
                apiReturn('删除失败');
            }
        } catch (\Exception $e) {
            write_error_log($e); //记录错误日志
            apiReturn(Error_Log);
        }
    }

    /**
     * 管理员列表
     * @method userlist
     * @author chengzhigang<1256699215@qq.com>
     */
    public function userlist(){
        //列表路径存cookie
        Cookie('Admin/userlist', request()->url());
        $filter = input('filter/s');
        $where = [];
        if (!empty($filter)) {
            $where[] = array('a.name|b.name', 'like', '%' . trim($filter) . '%');
        }
        $list = $this->AdminUserModel->alias('a')->join('admin_role b','a.role_id = b.id','left')->where($where)->field('a.id,a.username,a.name,b.name as role_name,a.head_pic,a.create_time')->order('a.create_time desc')->paginate(Page_Num);
        $this->assign('list', $list);
        $this->assign('filter', $filter);
        return $this->fetch();
    }

    /**
     * 新增管理员页面渲染
     * @method addUser
     * @author chengzhigang<1256699215@qq.com>
     */
    public function addUser(){
        if (request()->isGet()) {
            $rolelist = $this -> AdminRoleModel -> field('id,name')->select();
            $this->assign('rolelist', $rolelist);
            return $this->fetch();
        } else {
            try {
                $data = input('post.');
                //验证字段
                if(empty($data['username'])){
                    apiReturn('账号不能为空');
                }
                if(!check_username($data['username'])){
                    apiReturn('账号格式不正确');
                }
                if (empty($data['password'])) {
                    apiReturn('密码不能为空');
                }
                if(!check_password($data['password'])){
                    apiReturn('密码格式不正确');
                }else{
                    $data['password'] = encryptPwd($data['password']);
                }
                if (empty($data['name'])) {
                    apiReturn('姓名不能为空');
                }
                //验证账号唯一性
                $info = $this->AdminUserModel->where('username',$data['username'])->find();
                if(!empty($info)){
                    apiReturn('账号已存在');
                }
                //头像上传
                if(request()->file('head_pic')){
                    $filearr = upload(request()->file('head_pic'));
                    if($filearr['status']==1){
                        apiReturn($filearr['msg']);
                    }else{
                        $data['head_pic'] = $filearr['data']['path'];
                    }
                }
                $res = $this->AdminUserModel->createData($data);
                if ($res) {
                    //新增成功
                    apiReturn('新增成功', 0, array('url' => Cookie('Admin/userlist')));
                } else {
                    apiReturn('新增失败');
                }
            } catch (\Exception $e) {
                write_error_log($e); //记录错误日志
                apiReturn(Error_Log);
            }
        }
    }

    /**
     * 编辑管理员页面渲染
     * @method editUser
     * @author chengzhigang<1256699215@qq.com>
     */
    public function editUser(){
        if (request()->isGet()) {
            $id = input('id');
            $info = $this -> AdminUserModel -> where('id',$id)->find();
            $rolelist = $this -> AdminRoleModel -> field('id,name')->select();
            $this->assign('rolelist', $rolelist);
            $this->assign('info',$info);
            return $this->fetch();
        } else {
            try {
                $data = input('post.');
                //验证字段
                if (!empty($data['new_password'])) {
                    if(check_password($data['new_password'])){
                        $data['password'] = encryptPwd($data['new_password']);
                    }else{
                        apiReturn('密码格式不正确');
                    }
                }
                if (empty($data['name'])) {
                    apiReturn('姓名不能为空');
                }
                //头像上传
                if(request()->file('head_pic')){
                    $filearr = upload(request()->file('head_pic'));
                    if($filearr['status']==1){
                        apiReturn($filearr['msg']);
                    }else{
                        $data['head_pic'] = $filearr['data']['path'];
                    }
                }
                $res = $this->AdminUserModel->updateData(['id'=>$data['id']],$data);
                if ($res) {
                    //编辑成功
                    apiReturn('编辑成功', 0, array('url' => Cookie('Admin/userlist')));
                } else {
                    apiReturn('编辑失败');
                }
            } catch (\Exception $e) {
                write_error_log($e); //记录错误日志
                apiReturn(Error_Log);
            }
        }
    }

    /**
     * 删除管理员
     * @method delUser
     * @author chengzhigang<1256699215@qq.com>
     */
    public function delUser()
    {
        try {
            $id = input('id/d');
            $res = $this->AdminUserModel->where('id', $id)->delete();
            if ($res) {
                apiReturn('删除成功', 0, array('url' => Cookie('Admin/userlist')));
            } else {
                apiReturn('删除失败');
            }
        } catch (\Exception $e) {
            write_error_log($e); //记录错误日志
            apiReturn(Error_Log);
        }
    }

    /**
     * 图标列表页面渲染
     * @method fontlist
     * @author chengzhigang<1256699215@qq.com>
     */
    public function fontlist(){
        //列表路径存cookie
        Cookie('Admin/fontlist', request()->url());
        $filter = input('filter/s');
        $where = [];
        if (!empty($filter)) {
            $where[] = array('name|desc', 'like', '%' . trim($filter) . '%');
        }
        $list = $this->FontAwesomeModel->where($where)->field('id,name,desc')->paginate(70);
        $this->assign('list', $list);
        $this->assign('filter', $filter);
        return $this->fetch();
    }

    public function editFont(){
        try{
            $id = input('id');
            $data['desc'] = input('desc');
            $res = $this -> FontAwesomeModel -> updateData(array('id'=>$id),$data);
            if ($res) {
                apiReturn('编辑成功', 0, array('url' => Cookie('Admin/fontlist')));
            } else {
                apiReturn('编辑失败');
            }
        }catch(\Exception $e){
            write_error_log($e); //记录错误日志
            apiReturn(Error_Log);
        }
    }
}
