<?php
namespace app\index\controller;

use think\Db;

class Leave extends Base
{

    public function index()
    {
        Cookie('ReturnUrl', request()->url()); //记录当前路径
        $levelNum = $this->LeaveInfoModel->count(); //总留言数
        $this->assign('levelNum', $levelNum);
        return $this->fetch();
    }
    /**
     * 新增留言
     * @method addLeave
     * @author chengzhigang<1256699215@qq.com>
     * @param article_id content image
     * @return
     */
    public function addLeave()
    {
        if (request()->isPost()) {
            try {
                $data = input('post.');
                $user_id = session('user_id');
                $newData = [];
                if (empty($user_id)) {
                    apiReturn('请先登录博客');
                }
                if (!isset($data['content']) || !isset($data['pid'])) {
                    apiReturn('参数缺失');
                }
                if (!is_numeric($user_id) || !is_numeric($data['pid'])) {
                    apiReturn('非法参数');
                }
                if (request()->file('image')) {
                    $filearr = upload(request()->file('image'));
                    if ($filearr['status'] == 1) {
                        apiReturn($filearr['msg']);
                    } else {
                        $newData['image'] = $filearr['data']['path'];
                    }
                }
                Db::startTrans();
                $newData['user_id'] = $user_id;
                $newData['nickname'] = session('nickname');
                $newData['content'] = $data['content'];
                $newData['pid'] = $data['pid'];
                $newData['create_time'] = date('Y-m-d H:i:s');
                $newData['update_time'] = date('Y-m-d H:i:s');
                $res = $this->LeaveInfoModel->createData($newData);
                if ($res) {
                    $newData['id'] = $this->LeaveInfoModel->id;
                    $newData['likes'] = 0;
                    if(isset($newData['image'])&&!empty($newData['image'])){
                        $newData['image'] = (is_https()?'https://':'http://').Img_Url.'/'.$newData['image'];
                    }
                    $newData['child'] = [];
                    $newData['tonickname'] = "";
                    $newData['head_pic'] = $this->UserInfoModel->where('id', $newData['user_id'])->value('head_pic');
                    if ($data['pid']) {
                        $newData['tonickname'] = $this->LeaveInfoModel->alias('a')
                            ->join('user_info b', 'a.user_id=b.id', 'left')
                            ->where('a.id', $data['pid'])->value('b.nickname');
                        $content = '您对' . $newData['tonickname'] . '的留言进行了回复：' . $data['content'];
                    } else {
                        $content = '您对观海听潮进行了留言：' . $data['content'];
                    }
                    //更新留言列表
                    $this->redis->hset('leaveList', $newData['id'], json_encode($newData));
                    //清空留言列表缓存
                    $this->redis->set('LeaveTree', null);
                    //记录行为日志
                    $this->writeUserActiveLog($content);
                    Db::commit();
                    apiReturn('留言成功', 0, $newData);
                } else {
                    apiReturn('留言失败');
                }
            } catch (\Exception $e) {
                Db::rollback();
                write_error_log($e); //记录错误日志
                apiReturn(Error_Log);
            }
        } else {
            return $this->error(Error_404);
        }
    }
    /**
     * 留言列表
     * @method leaveList
     * @author chengzhigang<1256699215@qq.com>
     */
    public function leaveList()
    {
        try {
            $page = input('page/d', 1);
            $perpage = input('perpage/d', 10);
            $tree = [];
            if (!$this->redis->get('LeaveTree')) {
                $data = $this->redis->hgetall('leaveList');
                if (empty($data)) {
                    $data = $this->LeaveInfoModel->alias('a')->join('user_info b', 'a.user_id=b.id')->field('a.*,b.head_pic')->order('a.likes desc,a.create_time desc')->select()->toArray();
                    foreach ($data as $vv) {
                        $this->redis->hset('leaveList', $vv['id'], json_encode($vv));
                    }
                } else {
                    //数组排序
                    foreach ($data as &$vl) {
                        $vl = json_decode($vl, true);
                    }
                    if (!empty($data)) {
                        array_multisort(array_column($data, 'likes'), SORT_DESC, array_column($data, 'create_time'), SORT_DESC, $data);
                    }
                }
                foreach ($data as $vv) {
                    if ($vv['pid'] == 0) {
                        $tree[] = $this->getLeaveList($vv, $data);
                    }
                }
            } else {
                $tree = json_decode($this->redis->get('LeaveTree'), true);
            }
            $tree = array_values($tree);
            $tree = array_slice($tree, ($page - 1) * $perpage, $perpage);
            apiReturn('请求成功', 0, $tree);
        } catch (\Exception $e) {
            write_error_log($e); //记录错误日志
            apiReturn(Error_Log);
        }
    }
    /**
     * 获取留言列表
     * @method getLeaveList
     * @author chengzhigang<1256699215@qq.com>
     */
    public function getLeaveList($info, $data)
    {
        $info['child'] = [];
        foreach ($data as $v) {
            if ($v['pid'] == $info['id']) {
                $v['tonickname'] = $info['nickname'];
                $info['child'][] = $this->getLeaveList($v, $data);
            }
        }
        return $info;
    }

    /**
     * 留言点赞
     * @method leaveLike
     * @author chengzhigang<1256699215@qq.com>
     * @param pid
     * @return
     */
    public function leaveLike()
    {
        try {
            $pid = input('pid');
            $user_id = session('user_id');
            if (empty($user_id)) {
                apiReturn('请登录博客');
            }
            if (!$pid || !is_numeric($pid) || !is_numeric($user_id)) {
                apiReturn('非法参数');
            }
            //判断用户是否点过赞记录
            $result = $this->redis->hget("LeaveLikeLog", $user_id . ":" . $pid);
            $info = $this->redis->hget('leaveList', $pid);
            if ($info) {
                $info = json_decode($info, true);
                $likes = intval($info['likes']);
            } else {
                apiReturn('系统出错');
            }
            if ($result) {
                //之前点过赞啦取消点赞
                $likes = $likes - 1;
                $this->redis->zincrby('LeaveLikeNum', -1, $pid);
                $this->redis->hdel("LeaveLikeLog", $user_id . ":" . $pid);
                $msg = '取消点赞';
            } else {
                //数据库查询
                $likeInfo = $this->LeaveLikeModel->where('leave_id', $pid)->where('user_id', $user_id)->find();
                if ($likeInfo) {
                    //之前点过赞啦取消点赞
                    $likes = $likes - 1;
                    $this->redis->zincrby('LeaveLikeNum', -1, $pid);
                    //直接更改数据库
                    $this->LeaveLikeModel->where('leave_id', $pid)->where('user_id', $user_id)->delete();
                    $msg = '取消点赞';
                } else {
                    //该用户未点赞
                    $likes = $likes + 1;
                    $this->redis->zincrby('LeaveLikeNum', 1, $pid);
                    //记录点赞日志
                    $this->redis->hset("LeaveLikeLog", $user_id . ":" . $pid, date('Y-m-d H:i:s'));
                    $msg = '进行点赞';
                }
            }
            $info['likes'] = $likes;
            //更新留言列表
            $this->redis->hset('leaveList', $pid, json_encode($info));
            //清空留言列表缓存
            $this->redis->set('LeaveTree', null);
            //记录行为日志
            $content = '您对' . $info['nickname'] . '的留言' . $msg;
            $this->writeUserActiveLog($content);
            apiReturn('请求成功', 0, $likes);
        } catch (\Exception $e) {
            write_error_log($e); //记录错误日志
            apiReturn(Error_Log);
        }
    }

}
