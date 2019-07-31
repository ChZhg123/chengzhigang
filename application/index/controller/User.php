<?php
namespace app\index\controller;

class User extends Base
{

    public function index()
    {
        Cookie('ReturnUrl', request()->url()); //记录当前路径
        $user_id = session('user_id');
        if (empty($user_id)) {
            return redirect('index/index');
        }
        //点赞数
        $likes = $this->redis->zscore('UserLikeNum', session('user_id'));
        if (!$likes) {
            $likes = 0;
        }
        //评论数
        $comments = $this->redis->zscore('UserCommentNum', session('user_id'));
        if (!$comments) {
            $comments = 0;
        }
        //分享文章
        if (!$this->redis->get('UserForum_' . $user_id)) {
            $forum = [];
            $condition = [];
            $condition[] = ['a.check_status', 'eq', 2];
            $condition[] = ['a.type', 'eq', 1];
            $condition[] = ['a.user_id', 'eq', $user_id];
            $shareList = $this->ForumInfoModel->alias('a')->join('user_info b', 'a.user_id=b.id')->where($condition)->field('a.*,b.nickname,b.head_pic')->order('a.create_time desc')->select()->toArray();
            foreach ($shareList as &$val) {
                $commentCount = $this->redis->zscore('ForumCommentNum', $val['id']);
                $val['comments'] += ($commentCount ? $commentCount : 0);
            }
            //讨论文章
            $condition = [];
            $condition[] = ['a.check_status', 'eq', 2];
            $condition[] = ['a.type', 'eq', 2];
            $condition[] = ['a.user_id', 'eq', $user_id];
            $discussList = $this->ForumInfoModel->alias('a')->join('user_info b', 'a.user_id=b.id')->where($condition)->field('a.*,b.nickname,b.head_pic')->order('a.create_time desc')->select()->toArray();
            foreach ($discussList as &$val) {
                $commentCount = $this->redis->zscore('ForumCommentNum', $val['id']);
                $val['comments'] += ($commentCount ? $commentCount : 0);
            }
            $forum['share'] = $shareList;
            $forum['discuss'] = $discussList;
            $this->redis->setex('UserForum_' . $user_id, 3600, json_encode($forum));
        } else {
            $forum = json_decode($this->redis->get('UserForum_' . $user_id), true);
            $shareList = $forum['share'];
            $discussList = $forum['discuss'];
        }
        $this->assign('likes', $likes);
        $this->assign('comments', $comments);
        $this->assign('share', $shareList);
        $this->assign('discuss', $discussList);
        return $this->fetch();
    }

    public function userinfo()
    {
        Cookie('ReturnUrl', request()->url()); //记录当前路径
        $user_id = input('user_id/d');
        //点赞数
        $likes = $this->redis->zscore('UserLikeNum', $user_id);
        if (!$likes) {
            $likes = 0;
        }
        //评论数
        $comments = $this->redis->zscore('UserCommentNum', $user_id);
        if (!$comments) {
            $comments = 0;
        }
        $info = $this->UserInfoModel->where('id', $user_id)->find();
        if (empty($info)) {
            return $this->error(Error_404);
        }
        //文章评论
        if (!$this->redis->get('UserArticleComment_' . $user_id)) {
            $articleList = $this->ArticleCommentModel->alias('a')->join('article_info b', 'a.article_id=b.id')->where('a.user_id', $user_id)->field('a.*,b.title')->select()->toArray();
            foreach ($articleList as &$val) {
                if ($val['pid'] > 0) {
                    $commentInfo = $this->ArticleCommentModel->where('id', $val['pid'])->field('user_id,nickname')->find();
                    $val['pidname'] = $commentInfo['nickname'];
                    $val['piduser'] = $commentInfo['user_id'];
                } else {
                    $val['pidname'] = "";
                    $val['piduser'] = "";
                }
            }
            $this->redis->setex('UserArticleComment_' . $user_id, 3600, json_encode($articleList));
        } else {
            $articleList = json_decode($this->redis->get('UserArticleComment_' . $user_id), true);
        }
        //论坛文章评论
        if (!$this->redis->get('UserForumComment_' . $user_id)) {
            $forumList = $this->ForumCommentModel->alias('a')->join('forum_info b', 'a.forum_id=b.id')->where('a.user_id', $user_id)->where('b.check_status', 2)->field('a.*,b.title')->select()->toArray();
            foreach ($forumList as &$val) {
                if ($val['pid'] > 0) {
                    $commentInfo = $this->ForumCommentModel->where('id', $val['pid'])->field('user_id,nickname')->find();
                    $val['pidname'] = $commentInfo['nickname'];
                    $val['piduser'] = $commentInfo['user_id'];
                } else {
                    $val['pidname'] = "";
                    $val['piduser'] = "";
                }
            }
            $this->redis->setex('UserForumComment_' . $user_id, 3600, json_encode($forumList));
        } else {
            $forumList = json_decode($this->redis->get('UserForumComment_' . $user_id), true);
        }
        //分享文章
        if (!$this->redis->get('UserForum_' . $user_id)) {
            $forum = [];
            $condition = [];
            $condition[] = ['a.check_status', 'eq', 2];
            $condition[] = ['a.type', 'eq', 1];
            $condition[] = ['a.user_id', 'eq', $user_id];
            $shareList = $this->ForumInfoModel->alias('a')->join('user_info b', 'a.user_id=b.id')->where($condition)->field('a.*,b.nickname,b.head_pic')->order('a.create_time desc')->select()->toArray();
            foreach ($shareList as &$val) {
                $commentCount = $this->redis->zscore('ForumCommentNum', $val['id']);
                $val['comments'] += ($commentCount ? $commentCount : 0);
            }
            //讨论文章
            $condition = [];
            $condition[] = ['a.check_status', 'eq', 2];
            $condition[] = ['a.type', 'eq', 2];
            $condition[] = ['a.user_id', 'eq', $user_id];
            $discussList = $this->ForumInfoModel->alias('a')->join('user_info b', 'a.user_id=b.id')->where($condition)->field('a.*,b.nickname,b.head_pic')->order('a.create_time desc')->select()->toArray();
            foreach ($discussList as &$val) {
                $commentCount = $this->redis->zscore('ForumCommentNum', $val['id']);
                $val['comments'] += ($commentCount ? $commentCount : 0);
            }
            $forum['share'] = $shareList;
            $forum['discuss'] = $discussList;
            $this->redis->setex('UserForum_' . $user_id, 3600, json_encode($forum));
        } else {
            $forum = json_decode($this->redis->get('UserForum_' . $user_id), true);
            $shareList = $forum['share'];
            $discussList = $forum['discuss'];
        }
        $this->assign('likes', $likes);
        $this->assign('comments', $comments);
        $this->assign('info', $info);
        $this->assign('share', $shareList);
        $this->assign('discuss', $discussList);
        $this->assign('article', $articleList);
        $this->assign('forum', $forumList);
        return $this->fetch();
    }

    /**
     * 获取用户动态
     * @method getUserActive
     * @author chengzhigang<1256699215@qq.com>
     * @param
     * @return
     */
    public function getUserActive()
    {
        $page = input('page/d', 1);
        $user_id = session('user_id');
        $perpage = 15;
        $data = [];
        //用户行为日志
        $activeCount = $this->redis->llen('UserActiveLog_' . $user_id);
        for ($i = 0; $i < $activeCount; $i++) {
            $activeData = $this->redis->rpop('UserActiveLog_' . $user_id);
            if (!empty($activeData)) {
                $activeData = json_decode($activeData, true);
                $this->UserActiveLogModel->createData($activeData);
            }
        }
        //从数据库中查找行为日志
        $data = $this->UserActiveLogModel->where('user_id', $user_id)->field('user_id,nickname,content,create_time')->page($page, $perpage)->order('create_time desc')->select()->toArray();
        apiReturn('请求成功', 0, array('data' => $data, 'pages' => count($data)));
    }

    /**
     * 编辑用户资料
     * @method editUser
     * @author chengzhigang<1256699215@qq.com>
     * @param
     * @return
     */
    public function editUser()
    {
        if (request()->isPost()) {
            $data = input('post.');
            //图片上传
            if (request()->file('head_pic')) {
                $filearr = upload(request()->file('head_pic'));
                if ($filearr['status'] == 1) {
                    apiReturn($filearr['msg']);
                } else {
                    $data['head_pic'] = $filearr['data']['pathurl'];
                }
            }
            $user_id = session('user_id');
            if ($user_id) {
                $res = $this->UserInfoModel->updateData(array('id' => $user_id), $data);
                if ($res) {
                    session('user_info', $this->UserInfoModel->get($user_id));
                    apiReturn('编辑成功', 0, array('url' => Cookie('ReturnUrl')));
                } else {
                    apiReturn('编辑失败');
                }
            } else {
                apiReturn('编辑失败');
            }
        } else {
            return $this->error(Error_404);
        }
    }
}
