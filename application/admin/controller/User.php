<?php
/**
 * 用户管理
 * @author chengzhigang<1256699215@qq.com>
 */
namespace app\admin\controller;

class User extends Base
{

    /**
     * 用户列表渲染
     * @method userlist
     * @author chengzhigang<1256699215@qq.com>
     */
    public function userlist()
    {
        //列表路径存cookie
        Cookie('User/userlist', request()->url());
        $filter = input('filter/s');
        $where = [];
        if (!empty($filter)) {
            $where[] = array('nickname', 'like', '%' . trim($filter) . '%');
        }
        $list = $this->UserInfoModel->where($where)->field('id,nickname,head_pic,gender,login_type,sign,is_able,create_time')->order('create_time asc')->paginate(Page_Num);
        $this->assign('list', $list);
        $this->assign('filter', $filter);
        return $this->fetch();
    }

    /**
     * 查看用户信息
     * @method viewUser
     * @author chengzhigang<1256699215@qq.com>
     */
    public function viewUser(){
        $id = input('id/d');
        $userInfo = $this->UserInfoModel->where('id',$id)->find();
        //最新动态
        $activeList = $this->UserActiveLogModel->where('user_id',$id)->page(1,5)->order('create_time desc')->select()->toArray();
        //分享文章
        $forum = [];
        $condition = [];
        $condition[] = ['a.check_status','eq',2];
        $condition[] = ['a.type','eq',1];
        $condition[] = ['a.user_id','eq',$id];
        $shareList = $this->ForumInfoModel->alias('a')->join('user_info b','a.user_id=b.id')->where($condition)->field('a.*,b.nickname,b.head_pic')->order('a.create_time desc')->select()->toArray();
        foreach($shareList as &$val){
            $commentCount = $this->redis->zscore('ForumCommentNum',$val['id']);
            $val['comments'] += ($commentCount?$commentCount:0);
        }
        //讨论文章
        $condition = [];
        $condition[] = ['a.check_status','eq',2];
        $condition[] = ['a.type','eq',2];
        $condition[] = ['a.user_id','eq',$id];
        $discussList = $this->ForumInfoModel->alias('a')->join('user_info b','a.user_id=b.id')->where($condition)->field('a.*,b.nickname,b.head_pic')->order('a.create_time desc')->select()->toArray();
        foreach($discussList as &$val){
            $commentCount = $this->redis->zscore('ForumCommentNum',$val['id']);
            $val['comments'] += ($commentCount?$commentCount:0);
        }
        $this->assign('info',$userInfo);
        $this->assign('list',$activeList);
        $this->assign('share',$shareList);
        $this->assign('discuss',$discussList);
        return $this->fetch();
    }

    public function updateAble(){
        try{
            $id = input('id');
            $status = input('status');
            $res = $this->UserInfoModel->where('id',$id)->update(['is_able'=>$status]);
            if($res){
                apiReturn('修改成功', 0, array('url' => Cookie('User/userlist')));
            }else{
                apiReturn('修改失败');
            }
        }catch(\Exception $e){
            write_error_log($e); //记录错误日志
            apiReturn(Error_Log);
        }
    }

}
