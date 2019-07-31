<?php
/**
 * 内容管理
 * @author chengzhigang<1256699215@qq.com>
 */
namespace app\admin\controller;

use think\Db;

class Content extends Base
{
    /**
     * 留言列表
     * @method leavelist
     * @author chengzhigang<1256699215@qq.com>
     */
    public function leavelist()
    {
        //列表路径存cookie
        Cookie('Content/leavelist', request()->url());
        $filter = input('filter/s');
        $where = [];
        $where[] = array('a.user_id', 'neq', 1);
        if (!empty($filter)) {
            $where[] = array('a.nickname', 'like', '%' . trim($filter) . '%');
        }
        $list = $this->LeaveInfoModel->alias('a')->join('user_info b', 'a.user_id=b.id')->where($where)->field('a.*,b.head_pic')->order('a.create_time desc')->paginate(Page_Num);
        foreach ($list as $k => $val) {
            $val['pidname'] = $this->LeaveInfoModel->where('id', $val['pid'])->value('nickname');
            $list[$k] = $val;
        }
        $this->assign('list', $list);
        $this->assign('filter', $filter);
        return $this->fetch();
    }

    /**
     * 查看留言
     * @method viewLeave
     * @author chengzhigang<1256699215@qq.com>
     */
    public function viewLeave()
    {
        $id = input('id');
        $info = $this->LeaveInfoModel->where('id', $id)->find();
        $list = [];
        $list = $this->getLeaveList($info, $list);
        $this->assign('info', $info);
        $this->assign('list', $list);
        return $this->fetch();
    }

    public function getLeaveList($info, $list)
    {
        $data = $this->LeaveInfoModel->where('pid', $info['id'])->order('create_time desc')->select()->toArray();
        foreach ($data as &$val) {
            $val['tonickname'] = $info['nickname'];
            $list[] = $val;
            $list = $this->getLeaveList($val, $list);
        }
        return $list;
    }

    /**
     * 回复留言
     * @method replyLeave
     * @author chengzhigang<1256699215@qq.com>
     */
    public function replyLeave()
    {
        if (request()->isGet()) {
            $id = input('id');
            $info = $this->LeaveInfoModel->alias('a')->join('user_info b', 'a.user_id=b.id')->where('a.id', $id)->field('a.*,b.head_pic')->find();
            $this->assign('info', $info);
            return $this->fetch();
        } else {
            try {
                $pid = input('pid');
                $content = input('content');
                Db::startTrans();
                $newData['user_id'] = 1; //博客用户id
                $newData['nickname'] = NickName;
                $newData['content'] = $content;
                $newData['pid'] = $pid;
                $newData['create_time'] = date('Y-m-d H:i:s');
                $newData['update_time'] = date('Y-m-d H:i:s');
                $this->redis->incr('LeaveNum'); //添加留言数
                $res = $this->LeaveInfoModel->createData($newData);
                if ($res) {
                    $this->LeaveInfoModel->where('id', $pid)->update(array('reply_status' => 2));
                    $newData['id'] = $this->LeaveInfoModel->id;
                    $newData['likes'] = 0;
                    $newData['child'] = [];
                    $newData['tonickname'] = $this->LeaveInfoModel->alias('a')
                        ->join('user_info b', 'a.user_id=b.id', 'left')
                        ->where('a.id', $pid)->value('b.nickname');
                    $newData['head_pic'] = $this->UserInfoModel->where('id',$newData['user_id'])->value('head_pic');
                    $content = '您对' . $newData['tonickname'] . '的留言进行了回复：' . $content;
                    //更新留言列表
                    $this->redis->hset('leaveList', $newData['id'], json_encode($newData));
                    //清空留言列表缓存
                    $this->redis->set('LeaveTree', null);
                    //记录行为日志
                    $data = [];
                    $data['user_id'] = 1;
                    $data['nickname'] = NickName;
                    $data['content'] = $content;
                    $data['create_time'] = date('Y-m-d H:i:s');
                    $this->redis->rpush('UserActiveLog_1', json_encode($data));
                    Db::commit();
                    apiReturn('回复成功', 0, array('url' => Cookie('Content/leavelist')));
                } else {
                    apiReturn('回复失败');
                }
            } catch (\Exception $e) {
                Db::rollback();
                write_error_log($e); //记录错误日志
                apiReturn(Error_Log);
            }
        }
    }

    /**
     * 留言删除
     * @method delLeave
     * @author chengzhigang<1256699215@qq.com>
     */
    public function delLeave()
    {
        try {
            $id = input('id');
            $info = $this->LeaveInfoModel->where('id', $id)->find();
            Db::startTrans();
            $res = $this->LeaveInfoModel->where('id', $id)->delete();
            $list = $this->getLeaveList($info, []);
            foreach ($list as $val) {
                $this->LeaveInfoModel->where('id', $val['id'])->delete();
            }
            Db::commit();
            if ($res) {
                apiReturn('删除成功', 0, array('url' => Cookie('Content/leavelist')));
            } else {
                apiReturn('删除失败');
            }
        } catch (\Exception $e) {
            Db::rollback();
            write_error_log($e); //记录错误日志
            apiReturn(Error_Log);
        }
    }

    /**
     * 论坛审核
     * @method
     * @author chengzhigang<1256699215@qq.com>
     * @param
     * @return
     */
    public function auditlist()
    {
        //列表路径存cookie
        Cookie('Content/auditlist', request()->url());
        $filter = input('filter/s');
        $where = [];
        $where[] = array('check_status', 'eq', 1);
        if (!empty($filter)) {
            $where[] = array('b.nickname', 'like', '%' . trim($filter) . '%');
        }
        $list = $this->ForumInfoModel->alias('a')->join('user_info b', 'a.user_id=b.id')->where($where)->field('a.*,b.head_pic,b.nickname')->order('a.create_time desc')->paginate(Page_Num);
        $this->assign('list', $list);
        $this->assign('filter', $filter);
        return $this->fetch();
    }

    /**
     * 文章审核
     * @method checkAudit
     * @author chengzhigang<1256699215@qq.com>
     */
    public function checkAudit()
    {
        if (request()->isGet()) {
            $id = input('id');
            $info = $this->ForumInfoModel->where('id', $id)->find();
            $this->assign('info', $info);
            return $this->fetch();
        } else {
            try {
                $id = input('id');
                $data = [];
                $data['check_status'] = input('check_status');
                $data['check_time'] = date('Y-m-d H:i:s');
                $res = $this->ForumInfoModel->updateData(array('id'=>$id),$data);
                if ($res) {
                    //清空个人论坛文章缓存
                    if($data['check_status']==2){
                        $info = $this->ForumInfoModel->alias('a')->join('user_info b','a.user_id=b.id')->where('a.id',$id)->field('a.*,b.nickname,b.head_pic')->find();
                        $this->redis->set('UserForum_'.$info['user_id'],null);
                        setHomeForumList($info);
                    }
                    apiReturn('操作成功', 0, array('url' => Cookie('Content/auditlist')));
                } else {
                    apiReturn('操作失败');
                }
            } catch (\Exception $e) {
                write_error_log($e); //记录错误日志
                apiReturn(Error_Log);
            }
        }
    }

    /**
     * 论坛列表
     * @method forumlist
     * @author chengzhigang<1256699215@qq.com>
     */
    public function forumlist()
    {
        //列表路径存cookie
        Cookie('Content/forumlist', request()->url());
        $filter = input('filter/s');
        $where = [];
        $where[] = array('check_status', 'eq', 2);
        if (!empty($filter)) {
            $where[] = array('b.nickname|a.title', 'like', '%' . trim($filter) . '%');
        }
        $list = $this->ForumInfoModel->alias('a')->join('user_info b', 'a.user_id=b.id')->where($where)->field('a.*,b.head_pic,b.nickname')->order('a.create_time desc')->paginate(Page_Num);
        $this->assign('list', $list);
        $this->assign('filter', $filter);
        return $this->fetch();
    }

    /**
     * 文章查看
     * @method viewForum
     * @author chengzhigang<1256699215@qq.com>
     */
    public function viewForum()
    {
        $id = input('id');
        $info = $this->ForumInfoModel->alias('a')->join('user_info b','a.user_id=b.id')->where('a.id', $id)->field('a.*,b.nickname')->find();
        $this->assign('info', $info);
        return $this->fetch();
    }

    /**
     * 论坛删除
     * @method delForum
     * @author chengzhigang<1256699215@qq.com>
     */
    public function delForum(){
        try {
            $id = input('id');
            $info = $this->ForumInfoModel->where('id', $id)->find();
            if($info['stick']==2){
                //清空置顶文章缓存
                $this->redis->set('Forumstick',null);
            }
            //删除列表数据
            $this->redis->zRem('HomeForumList_1',$id);
            $this->redis->zRem('HomeForumList_2',$id);
            $this->redis->zRem('HomeForumList_3',$id);
            $this->redis->zRem('HomeForumList_4',$id);
            $this->redis->zRem('HomeForumList_5',$id);
            $this->redis->hDel('HomeForumList',$id);
            //清空个人缓存
            $this->redis->set('UserForum_'.$info['user_id'],null);
            $res = $this->ForumInfoModel->where('id', $id)->delete();
            if ($res) {
                apiReturn('删除成功', 0, array('url' => Cookie('Content/forumlist')));
            } else {
                apiReturn('删除失败');
            }
        } catch (\Exception $e) {
            write_error_log($e); //记录错误日志
            apiReturn(Error_Log);
        }
    }

    /**
     * 更新文章状态
     * @method changeForum
     * @author chengzhigang<1256699215@qq.com>
     */
    public function changeForum(){
        try{
            $id = input('id');
            $field = input('field/s');
            $value = input('value');
            $res = $this->ForumInfoModel->updateData(array('id'=>$id),array($field=>$value));
            if($res){
                $this->redis->set('Forumstick',null);
                if($field=='recomd'){
                    $info = $this->ForumInfoModel->alias('a')->join('user_info b','a.user_id=b.id')->where('a.id',$id)->field('a.*,b.nickname,b.head_pic')->find();
                    if($value==1){
                        $this->redis->zRem('HomeForumList_5',$id);
                    }else{
                        $min = strtotime(date('Y-m-d'));
                        $time = strtotime($info['create_time'])-$min;
                        $this->redis->zAdd('HomeForumList_5',$time,$id);
                    }
                    $this->redis->hSet('HomeForumList',$id,json_encode($info));
                    //清空个人缓存
                    $this->redis->set('UserForum_'.$info['user_id'],null);
                }
                apiReturn('更改成功', 0, array('url' => Cookie('Content/forumlist')));
            }else{
                apiReturn('更改失败');
            }
        }catch(\Exception $e){
            write_error_log($e); //记录错误日志
            apiReturn(Error_Log);
        }
    }
}
