<?php
namespace app\index\controller;

use Qiniu\Auth;
use Qiniu\Config;
use Qiniu\Storage\UploadManager;
use Qiniu\Zone;
use think\Db;
use think\facade\Env;

class Forum extends Base
{

    public function index()
    {
        Cookie('ReturnUrl', request()->url()); //记录当前路径
        $type = input('type/d', 1);
        //获取置顶文章
        if (!$this->redis->get('Forumstick')) {
            $condition = [];
            $condition[] = ['a.stick', 'eq', 2];
            $condition[] = ['a.check_status', 'eq', 2];
            $stick = $this->ForumInfoModel->alias('a')->join('user_info b', 'a.user_id=b.id')->where($condition)->field('a.*,b.nickname,b.head_pic')->order('a.update_time desc')->select()->toArray();
            $this->redis->setex('Forumstick', 3600, json_encode($stick));
        } else {
            $stick = json_decode($this->redis->get('Forumstick'), true);
        }
        //获取文章列表
        $list = $this->redis->zRevRange('HomeForumList_' . $type, 0, 14);
        $data = [];
        $count = $this->redis->zCard('HomeForumList_' . $type);
        foreach ($list as $val) {
            $info = $this->redis->hGet('HomeForumList', $val);
            if ($info) {
                $info = json_decode($info, true);
                if ($info) {
                    $commentCount = $this->redis->zscore('ForumCommentNum', $info['id']);
                    $info['create_time'] = getCreateTimeAttr($info['create_time']);
                    $info['comments'] += ($commentCount ? $commentCount : 0);
                    $data[] = $info;
                }
            }
        }
        $this->assign('stick', $stick);
        $this->assign('list', $data);
        $this->assign('type', $type);
        $this->assign('count', $count);
        return $this->fetch();
    }

    /**
     * 获取论坛列表
     * @method getForumList
     * @author chengzhigang<1256699215@qq.com>
     */
    public function getForumList()
    {
        try {
            $type = input('type/d', 1);
            $page = input('page/d', 1);
            $perpage = input('perpage/d', 15);
            //获取文章列表
            $start = ($page - 1) * $perpage;
            $end = $start + $perpage - 1;
            $list = $this->redis->zRevRange('HomeForumList_' . $type, $start, $end);
            $data = [];
            $count = $this->redis->zCard('HomeForumList_' . $type);
            foreach ($list as $val) {
                $info = $this->redis->hGet('HomeForumList', $val);
                if ($info) {
                    $info = json_decode($info, true);
                    $commentCount = $this->redis->zscore('ForumCommentNum', $info['id']);
                    $info['create_time'] = getCreateTimeAttr($info['create_time']);
                    $info['comments'] += ($commentCount ? $commentCount : 0);
                    $data[] = $info;
                }
            }
            apiReturn('请求成功', 0, $data);
            return $this->fetch();
        } catch (\Exception $e) {
            write_error_log($e); //记录错误日志
            apiReturn(Error_Log);
        }
    }

    public function forumInfo()
    {
        Cookie('ReturnUrl', request()->url()); //记录当前路径
        $id = input('id'); //文章id
        $focus = input('focus');
        $user_id = session('user_id'); //用户id
        $info = $this->getForumInfo($id); //获取文章详情
        if (!$info) {
            return $this->error(Error_404);
        }
        //记录浏览数
        $this->redis->zincrby('ForumHitNum', 1, $id);
        $info['hits'] += 1;
        //点击量增1，用于综合排序
        $this->redis->zIncrBy('HomeForumList_1', 1, $id);
        $count = $this->ForumCommentModel->where('forum_id', $id)->where('pid', 0)->count();
        $this->assign('count', $count);
        $this->assign('info', $info);
        $this->assign('focus', $focus);
        return $this->fetch();
    }

    /**
     * 点赞功能
     * @method forumLike
     * @author chengzhigang<1256699215@qq.com>
     * @param user_id id
     * @return
     */
    public function forumLike()
    {
        try {
            $user_id = session('user_id');
            $forum_id = input('id');
            if (empty($user_id)) {
                apiReturn('请登录博客');
            }
            if (!is_numeric($user_id) || !$forum_id || !is_numeric($forum_id)) {
                apiReturn('非法参数');
            }
            $info = $this->getForumInfo($forum_id);
            if (!$info) {
                apiReturn('文章不存在');
            }
            $msg = "";
            //判断用户是否点过赞记录
            $result = $this->redis->hget("ForumLikeLog", $user_id . ":" . $forum_id);
            if ($result) {
                //之前点过赞啦取消点赞
                $this->redis->zincrby('ForumLikeNum', -1, $forum_id);
                $this->redis->zincrby('UserLikeNum', -1, $user_id);
                $this->redis->zIncrBy('HomeForumList_1', -1, $forum_id);
                $this->redis->hdel("ForumLikeLog", $user_id . ":" . $forum_id);
                $msg = "取消点赞";
            } else {
                //数据库查询
                $likeInfo = $this->ForumLikeModel->where('forum_id', $forum_id)->where('user_id', $user_id)->find();
                if ($likeInfo) {
                    $this->redis->zincrby('ForumLikeNum', -1, $forum_id);
                    $this->redis->zincrby('UserLikeNum', -1, $user_id);
                    $this->redis->zIncrBy('HomeForumList_1', -1, $forum_id);
                    $msg = "取消点赞";
                    //直接更改数据库
                    $this->ForumLikeModel->where('id', $likeInfo['id'])->delete();
                } else {
                    //点赞
                    $this->redis->zincrby('ForumLikeNum', 1, $forum_id);
                    $this->redis->zincrby('UserLikeNum', 1, $user_id);
                    $this->redis->zIncrBy('HomeForumList_1', 1, $forum_id);
                    $this->redis->hset("ForumLikeLog", $user_id . ":" . $forum_id, date('Y-m-d H:i:s'));
                    $msg = "进行点赞";
                }
            }
            //记录行为日志
            $this->writeUserActiveLog('您对文章' . $info['title'] . $msg);
            apiReturn('请求成功', 0, array('likeNum' => $info['likes']));
        } catch (\Exception $e) {
            write_error_log($e); //记录错误日志
            apiReturn(Error_Log);
        }
    }

    /**
     * 评论列表
     * @method forumCommentList
     * @author chengzhigang<1256699215@qq.com>
     */
    public function forumCommentList()
    {
        try {
            $id = input('id/d');
            $page = input('page/d', 1);
            $perpage = input('perpage/d', 15);
            if (!$id || !is_numeric($id)) {
                apiReturn('非法参数');
            }
            $list = $this->ForumCommentModel->alias('a')->join('user_info b', 'a.user_id=b.id')->cache(true, 300)->where('a.forum_id', $id)->where('a.pid', 0)->field('a.*,b.head_pic')->page($page, $perpage)->order('a.likes desc,a.create_time desc')->select()->toArray();
            foreach ($list as $key => $vv) {
                $list[$key]['likes'] += $this->redis->zscore('ForumCommentLikeNum', $id . ":" . $vv['id']);
                $list[$key]['child'] = $this->getForumCommentList($vv['id'], $vv['nickname']);
                $list[$key]['create_msg'] = getCreateTimeAttr($vv['create_time']);
                $likes[$key] = $list[$key]['likes'];
                $create[$key] = $list[$key]['create_time'];
            }
            if (!empty($list)) {
                array_multisort($likes, SORT_DESC, $create, SORT_DESC, $list);
            }
            apiReturn('请求成功', 0, $list);
        } catch (\Exception $e) {
            write_error_log($e); //记录错误日志
            apiReturn(Error_Log);
        }
    }

    /**
     * 评论点赞
     * @method commentLike
     * @author chengzhigang<1256699215@qq.com>
     * @param pid
     * @return
     */
    public function commentLike()
    {
        try {
            $pid = input('pid');
            $user_id = session('user_id');
            $forum_id = input('id');
            if (empty($user_id)) {
                apiReturn('请登录博客');
            }
            if (!is_numeric($user_id) || !$forum_id || !is_numeric($forum_id) || !is_numeric($pid)) {
                apiReturn('非法参数');
            }
            $info = $this->getForumInfo($forum_id);
            if (!$info) {
                apiReturn('文章不存在');
            }
            //判断用户是否点过赞记录
            $result = $this->redis->hget("ForumCommentLikeLog", $user_id . ":" . $forum_id . ":" . $pid);
            if ($result) {
                //之前点过赞啦取消点赞
                $this->redis->zincrby('ForumCommentLikeNum', -1, $forum_id . ":" . $pid);
                $this->redis->zincrby('UserLikeNum', -1, $user_id);
                $this->redis->hdel("ForumCommentLikeLog", $user_id . ":" . $forum_id . ":" . $pid);
                $msg = '取消点赞';
            } else {
                //数据库查询
                $likeInfo = $this->ForumLikeModel->where('forum_id', $forum_id)->where('pid', $pid)->where('user_id', $user_id)->find();
                if ($likeInfo) {
                    //之前点过赞啦取消点赞
                    $this->redis->zincrby('ForumCommentLikeNum', -1, $forum_id . ":" . $pid);
                    $this->redis->zincrby('UserLikeNum', -1, $user_id);
                    //直接更改数据库
                    $this->ForumLikeModel->where('id', $likeInfo['id'])->delete();
                    $msg = '取消点赞';
                } else {
                    //该用户未点赞
                    $this->redis->zincrby('ForumCommentLikeNum', 1, $forum_id . ":" . $pid);
                    $this->redis->zincrby('UserLikeNum', 1, $user_id);
                    //记录点赞日志
                    $this->redis->hset("ForumCommentLikeLog", $user_id . ":" . $forum_id . ":" . $pid, date('Y-m-d H:i:s'));
                    $msg = '进行点赞';
                }
            }
            $CommentLikeNum = $this->redis->zscore('ForumCommentLikeNum', $forum_id . ":" . $pid);
            if (!$CommentLikeNum) {
                $CommentLikeNum = 0;
            }
            $oldLikeInfo = $info['comment'][$pid];
            $realLikeNum = intval($oldLikeInfo['likes']) + intval($CommentLikeNum);
            //记录行为日志
            $content = '在文章' . $info['title'] . '中，您对' . $oldLikeInfo['nickname'] . '的评论' . $msg;
            $this->writeUserActiveLog($content);
            apiReturn('请求成功', 0, $realLikeNum);
        } catch (\Exception $e) {
            write_error_log($e); //记录错误日志
            apiReturn(Error_Log);
        }
    }

    /**
     * 获取评论列表
     * @method getForumCommentList
     * @author chengzhigang<1256699215@qq.com>
     */
    public function getForumCommentList($pid = 0, $tomail)
    {
        $data = $this->ForumCommentModel->alias('a')->join('user_info b', 'a.user_id=b.id')->cache(true, 300)->where('a.pid', $pid)->field('a.*,b.head_pic')->order('a.likes desc,a.create_time desc')->select()->toArray();
        if (count($data) == 0) {
            return $data;
        }
        foreach ($data as $k => $val) {
            $data[$k]["child"] = [];
            $data[$k]['likes'] += $this->redis->zscore('ForumCommentLikeNum', $val['forum_id'] . ":" . $val['id']);
            $data[$k]['tonickname'] = $tomail;
            $data[$k]['create_msg'] = getCreateTimeAttr($val['create_time']);
            $tree = $this->getForumCommentList($val["id"], $data[$k]['nickname']);
            if (!empty($tree)) {
                $data[$k]["child"] = $tree;
            }
        }
        return $data;
    }

    /**
     * 新增评论
     * @method addComment
     * @author chengzhigang<1256699215@qq.com>
     * @param article_id content image
     * @return
     */
    public function addComment()
    {
        if (request()->isPost()) {
            try {
                $data = input('post.');
                $user_id = session('user_id');
                $newData = [];
                if (empty($user_id)) {
                    apiReturn('请先登录博客');
                }
                if (!is_numeric($user_id) || !$data['forum_id'] || !is_numeric($data['forum_id']) || !is_numeric($data['pid'])) {
                    apiReturn('非法参数');
                }
                $info = $this->getForumInfo($data['forum_id']);
                if (!$info) {
                    apiReturn('文章不存在');
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
                $newData['forum_id'] = $data['forum_id'];
                $newData['pid'] = $data['pid'];
                $newData['create_time'] = date('Y-m-d H:i:s');
                $newData['update_time'] = date('Y-m-d H:i:s');
                $this->redis->zincrby('ForumCommentNum', 1, $data['forum_id']); //添加评论数
                $this->redis->zincrby('UserCommentNum', 1, $user_id);
                $res = $this->ForumCommentModel->createData($newData);
                if ($res) {
                    $newData['id'] = $this->ForumCommentModel->id;
                    $newData['likes'] = 0;
                    $newData['create_msg'] = getCreateTimeAttr($newData['create_time']);
                    if(isset($newData['image'])&&!empty($newData['image'])){
                        $newData['image'] = (is_https()?'https://':'http://').Img_Url.'/'.$newData['image'];
                    }
                    $newData['head_pic'] = $this->UserInfoModel->where('id', $newData['user_id'])->value('head_pic');
                    if (!isset($info['comment'][$newData['id']])) {
                        $info['comment'][$newData['id']] = $newData;
                        $this->redis->setex('ForumInfo_' . $newData['forum_id'], 3600, json_encode($info));
                    }
                    $newData['child'] = [];
                    $newData['tonickname'] = $this->ForumCommentModel->alias('a')
                        ->join('user_info b', 'a.user_id=b.id', 'left')
                        ->where('a.id', $data['pid'])->value('b.nickname');
                    if ($data['pid']) {
                        $content = '在文章' . $info['title'] . '中，您对' . $newData['tonickname'] . '的评论进行了回复：' . $data['content'];
                    } else {
                        $content = '您对文章' . $info['title'] . '进行了评论：' . $data['content'];
                    }
                    //记录行为日志
                    $this->writeUserActiveLog($content);
                    //清空个人论坛评论缓存
                    $this->redis->set('UserForumComment_' . $user_id, null);
                    Db::commit();
                    apiReturn('评论成功', 0, $newData);
                } else {
                    apiReturn('评论失败');
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
     * 论坛富文本图片上传
     * @method uploadQiniu
     * @author chengzhigang<1256699215@qq.com>
     */
    public function uploadQiniu()
    {
        if (request()->isPost()) {
            $file = request()->file('imgFile');
            if ($file->isValid()) {
                $info = $file->getInfo();
                $fileName = $info['name'];
                $realPath = $info['tmp_name'];
                $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                $size = $info['size'];
                $newName = 'forum/' . md5(microtime(true)) . '.' . $ext;
                $default = config('upload.type');
                $defaultConfig = $default['image'];
                $fileExt = isset($defaultConfig['ext']) ? $defaultConfig['ext'] : $config['ext'];
                $fileSize = isset($defaultConfig['size']) ? $defaultConfig['size'] : $config['size'];
                if (strpos($fileExt, $ext) !== false) {
                    //文件大小验证
                    if ($size > $fileSize) {
                        exit(json_encode(array('error' => 1, 'message' => '请上传' . floatval($fileSize) / (1024 * 1024) . 'M以内的文件')));
                    }
                    $result = checkSensitive($realPath, 1);
                    if ($result['status'] != 1) {
                        exit(json_encode(array('error' => 1, 'message' => '图片不合规')));
                    }
                    //添加水印
                    $image = \think\Image::open($realPath);
                    $waterFont = isset($config['water']['font']) ? $config['water']['font'] : $defaultConfig['water']['font'];
                    $waterFile = isset($waterFont['file']) ? $waterFont['file'] : $defaultConfig['water']['font']['file'];
                    $waterText = NickName . ' @' . session('nickname');
                    $waterSize = isset($waterFont['size']) ? $waterFont['size'] : $defaultConfig['water']['font']['size'];
                    $waterColor = isset($waterFont['color']) ? $waterFont['color'] : $defaultConfig['water']['font']['color'];
                    $image->text($waterText, Env::get('root_path') . $waterFile, $waterSize, $waterColor, 9, -15)->save($realPath);
                    //上传七牛云
                    $qiniu = config('upload.qiniu');
                    require_once '../vendor/qiniu/php-sdk/autoload.php';
                    $auth = new Auth($qiniu['access_key'], $qiniu['secret_key']);
                    // 生成上传Token
                    $token = $auth->uploadToken($qiniu['bucket']);
                    $zone = new Zone(array('upload-z1.qiniup.com')); //默认域名是华北地区
                    $cfg = new Config($zone);
                    $uploadMgr = new UploadManager($cfg);
                    list($ret, $err) = $uploadMgr->putFile($token, $newName, $realPath);
                    if ($err !== null) {
                        exit(json_encode(array('error' => 1, 'message' => $err->getError())));
                    } else {
                        $pathUrl = $qiniu['domain_name'] . '/' . $ret['key'];
                        exit(json_encode(array('error' => 0, 'url' => $pathUrl)));
                    }
                } else {
                    exit(json_encode(array('error' => 1, 'message' => '文件类型不支持')));
                }
            } else {
                return $this->error(Error_404);
            }
        }
    }

    /**
     * 发表文章
     * @method addForum
     * @author chengzhigang<1256699215@qq.com>
     * @param
     * @return
     */
    public function addForum()
    {
        if (request()->isGet()) {
            Cookie('ReturnUrl', request()->url()); //记录当前路径
            return $this->fetch();
        } else {
            try {
                $data = [];
                $content = request()->post('content');
                $type = input('type/d');
                $title = request()->post('title');
                $user_id = session('user_id');
                if (empty($user_id)) {
                    apiReturn('请先登录博客');
                }
                if (empty($title)) {
                    apiReturn('请输入文章标题');
                }
                if (empty($content)) {
                    apiReturn('请填写文章内容');
                }
                if (!is_numeric($user_id) || !in_array($type, [1, 2])) {
                    apiReturn('非法参数');
                }
                if ($type == 1) {
                    $data['check_status'] = 1;
                } else {
                    $data['check_status'] = 2;
                }
                Db::startTrans();
                $data['user_id'] = $user_id;
                $data['title'] = $title;
                $data['content'] = $content;
                $data['type'] = $type;
                $res = $this->ForumInfoModel->createData($data);
                if ($res) {
                    //记录行为日志
                    $this->writeUserActiveLog('发表论坛文章：' . $title);
                    //清空个人论坛文章缓存
                    if ($data['check_status'] == 2) {
                        $this->redis->set('UserForum_' . $user_id, null);
                        $id = $this->ForumInfoModel->id;
                        $info = $this->ForumInfoModel->alias('a')->join('user_info b', 'a.user_id=b.id')->where('a.id', $id)->field('a.*,b.nickname,b.head_pic')->find();
                        setHomeForumList($info);
                    }
                    Db::commit();
                    apiReturn($type == 1 ? '发表成功,正在审核中' : '发表成功', 0, array('url' => empty(Cookie('ReturnUrl')) ? 'index' : Cookie('ReturnUrl')));
                } else {
                    apiReturn('发表失败');
                }
            } catch (\Exception $e) {
                Db::rollback();
                write_error_log($e); //记录错误日志
                apiReturn(Error_Log);
            }
        }
    }
}
