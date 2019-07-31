<?php
namespace app\index\controller;

use think\Db;

class Article extends Base
{

    /**
     * 文章详情页面输出
     * @method index
     * @author chengzhigang<1256699215@qq.com>
     */
    public function index()
    {
        Cookie('ReturnUrl', request()->url()); //记录当前路径
        $id = input('id'); //文章id
        $focus = input('focus');
        $user_id = session('user_id'); //用户id
        $info = $this->getArticleInfo($id); //获取文章详情
        if (!$info) {
            return $this->error(Error_404);
        }
        //记录浏览数
        $this->redis->zincrby('ArticleHitNum', 1, $id);
        $info['hits'] += 1;
        //记录浏览日志（用于猜你喜欢）
        if ($user_id) {
            $this->redis->rpush('ArticleHits', json_encode(array('article_id' => $id, 'user_id' => $user_id, 'time' => date('Y-m-d H:i:s'))));
        }
        $count = $this->ArticleCommentModel->where('article_id', $id)->where('pid', 0)->count();
        $this->assign('info', $info);
        $this->assign('focus', $focus);
        $this->assign('count', $count);
        return $this->fetch();
    }

    /**
     * 点赞功能
     * @method articleLike
     * @author chengzhigang<1256699215@qq.com>
     * @param user_id type id
     * @return
     */
    public function articleLike()
    {
        try {
            $user_id = session('user_id');
            $article_id = input('id');
            $type = input('type'); //1点赞 2踩
            $articleNum = 0;
            if (empty($user_id)) {
                apiReturn('请登录博客');
            }
            $msg = "";
            if (!$article_id || !is_numeric($article_id) || !is_numeric($user_id) || !in_array($type, [1, 2])) {
                apiReturn('非法参数');
            }
            $info = $this->getArticleInfo($article_id);
            if (!$info) {
                apiReturn('文章不存在');
            }
            //判断用户是否点过赞记录
            $result = $this->redis->hget("ArticleLikeHateLog", $user_id . ":" . $article_id);
            if ($result) {
                $result = json_decode($result, true);
                if ($type == 1) {
                    if ($result['type'] == 1) {
                        //之前点过赞啦取消点赞
                        $this->redis->zincrby('ArticleLikeNum', -1, $article_id);
                        $this->redis->zincrby('UserLikeNum', -1, $user_id);
                        $this->redis->hdel("ArticleLikeHateLog", $user_id . ":" . $article_id);
                        $msg = "取消点赞";
                    } else {
                        $this->redis->zincrby('ArticleLikeNum', 1, $article_id);
                        $this->redis->zincrby('UserLikeNum', 1, $user_id);
                        $this->redis->zincrby('ArticleHateNum', -1, $article_id);
                        $msg = "进行点赞";
                        //记录点赞踩日志
                        $this->redis->hset("ArticleLikeHateLog", $user_id . ":" . $article_id, json_encode(array('type' => $type, 'time' => date('Y-m-d H:i:s'))));
                    }
                } else {
                    if ($result['type'] == 1) {
                        $this->redis->zincrby('ArticleHateNum', 1, $article_id);
                        $this->redis->zincrby('ArticleLikeNum', -1, $article_id);
                        $this->redis->zincrby('UserLikeNum', -1, $user_id);
                        $msg = "踩了一脚";
                        //记录点赞踩日志
                        $this->redis->hset("ArticleLikeHateLog", $user_id . ":" . $article_id, json_encode(array('type' => $type, 'time' => date('Y-m-d H:i:s'))));
                    } else {
                        //之前踩过取消踩
                        $this->redis->zincrby('ArticleHateNum', -1, $article_id);
                        $this->redis->hdel("ArticleLikeHateLog", $user_id . ":" . $article_id);
                        $msg = "取消了踩";
                    }
                }
            } else {
                //数据库查询
                $likeInfo = $this->ArticleLikeModel->where('article_id', $article_id)->where('user_id', $user_id)->find();
                if ($likeInfo) {
                    if ($type == 1) {
                        //点赞
                        if ($likeInfo['type'] == 1) {
                            //之前点过赞啦取消点赞
                            $this->redis->zincrby('ArticleLikeNum', -1, $article_id);
                            $this->redis->zincrby('UserLikeNum', -1, $user_id);
                            $msg = "取消点赞";
                            //直接更改数据库
                            $this->ArticleLikeModel->where('article_id', $article_id)->where('user_id', $user_id)->delete();
                        } else {
                            $this->redis->zincrby('ArticleLikeNum', 1, $article_id);
                            $this->redis->zincrby('UserLikeNum', 1, $user_id);
                            $this->redis->zincrby('ArticleHateNum', -1, $article_id);
                            $msg = "进行点赞";
                            //直接更改数据库
                            $this->ArticleLikeModel->where('article_id', $article_id)->where('user_id', $user_id)->update(array('type' => $type, 'update_time' => date('Y-m-d H:i:s')));
                        }
                    } else {
                        //踩
                        if ($likeInfo['type'] == 2) {
                            //之前踩过取消踩
                            $this->redis->zincrby('ArticleHateNum', -1, $article_id);
                            $msg = "取消了踩";
                            //直接更改数据库
                            $this->ArticleLikeModel->where('article_id', $article_id)->where('user_id', $user_id)->delete();
                        } else {
                            $this->redis->zincrby('ArticleHateNum', 1, $article_id);
                            $this->redis->zincrby('ArticleLikeNum', -1, $article_id);
                            $this->redis->zincrby('UserLikeNum', -1, $user_id);
                            $msg = "踩了一脚";
                            //直接更改数据库
                            $this->ArticleLikeModel->where('article_id', $article_id)->where('user_id', $user_id)->update(array('type' => $type, 'update_time' => date('Y-m-d H:i:s')));
                        }
                    }
                } else {
                    //该用户未点赞
                    if ($type == 1) {
                        $this->redis->zincrby('ArticleLikeNum', 1, $article_id);
                        $this->redis->zincrby('UserLikeNum', 1, $user_id);
                        $msg = "进行点赞";
                    } else {
                        $this->redis->zincrby('ArticleHateNum', 1, $article_id);
                        $msg = "踩了一脚";
                    }
                    //记录点赞踩日志
                    $this->redis->hset("ArticleLikeHateLog", $user_id . ":" . $article_id, json_encode(array('type' => $type, 'time' => date('Y-m-d H:i:s'))));
                }
            }
            //记录行为日志
            $this->writeUserActiveLog('您对文章' . $info['title'] . $msg);
            apiReturn('请求成功', 0, array('likeNum' => $info['likes'], 'hateNum' => $info['hates']));
        } catch (\Exception $e) {
            write_error_log($e); //记录错误日志
            apiReturn(Error_Log);
        }
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
                if (!isset($data['article_id']) || !isset($data['content']) || !isset($data['pid'])) {
                    apiReturn('参数缺失');
                }
                if (!is_numeric($user_id) || !$data['article_id'] || !is_numeric($data['article_id']) || !is_numeric($data['pid'])) {
                    apiReturn('非法参数');
                }
                $info = $this->getArticleInfo($data['article_id']);
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
                $newData['article_id'] = $data['article_id'];
                $newData['pid'] = $data['pid'];
                $newData['create_time'] = date('Y-m-d H:i:s');
                $newData['update_time'] = date('Y-m-d H:i:s');
                $this->redis->zincrby('ArticleCommentNum', 1, $data['article_id']); //添加评论数
                $this->redis->zincrby('UserCommentNum', 1, $user_id);
                $res = $this->ArticleCommentModel->createData($newData);
                if ($res) {
                    $newData['id'] = $this->ArticleCommentModel->id;
                    $newData['likes'] = 0;
                    if(isset($newData['image'])&&!empty($newData['image'])){
                        $newData['image'] = (is_https()?'https://':'http://').Img_Url.'/'.$newData['image'];
                    }
                    $newData['create_time'] = getCreateTimeAttr($newData['create_time']);
                    $newData['head_pic'] = $this->UserInfoModel->where('id', $newData['user_id'])->value('head_pic');
                    if (!isset($info['comment'][$newData['id']])) {
                        $info['comment'][$newData['id']] = $newData;
                        array_multisort(array_column($info['comment'], 'likes'), SORT_DESC, array_column($info['comment'], 'create_time'), SORT_DESC, $info['comment']);
                        $this->redis->setex('ArticleInfo_' . $newData['article_id'], 3600, json_encode($info));
                    }
                    $newData['child'] = [];
                    $newData['tonickname'] = $this->ArticleCommentModel->alias('a')
                        ->join('user_info b', 'a.user_id=b.id', 'left')
                        ->where('a.id', $data['pid'])->value('b.nickname');
                    if ($data['pid']) {
                        $content = '在文章' . $info['title'] . '中，您对' . $newData['tonickname'] . '的评论进行了回复：' . $data['content'];
                    } else {
                        $content = '您对文章' . $info['title'] . '进行了评论：' . $data['content'];
                    }
                    //记录行为日志
                    $this->writeUserActiveLog($content);
                    //清空个人评论缓存
                    $this->redis->set('UserArticleComment_' . $user_id, null);
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
     * 评论列表
     * @method commentList
     * @author chengzhigang<1256699215@qq.com>
     */
    public function commentList()
    {
        try {
            $id = input('id');
            $page = input('page');
            $perpage = input('perpage');
            $list = [];
            if ($id && is_numeric($id)) {
                $list = $this->ArticleCommentModel->alias('a')->join('user_info b', 'a.user_id=b.id')->cache(true, 300)->where('a.article_id', $id)->where('a.pid', 0)->field('a.*,b.head_pic')->page($page, $perpage)->order('a.likes desc,a.create_time desc')->select()->toArray();
                foreach ($list as $key => $vv) {
                    $list[$key]['likes'] += $this->redis->zscore('CommentLikeNum', $id . ":" . $vv['id']);
                    $list[$key]['child'] = $this->getCommentList($vv['id'], $vv['nickname']);
                    $likes[$key] = $list[$key]['likes'];
                    $create[$key] = $list[$key]['create_time'];
                }
                if (!empty($list)) {
                    array_multisort($likes, SORT_DESC, $create, SORT_DESC, $list);
                }
            }
            apiReturn('请求成功', 0, $list);
        } catch (\Exception $e) {
            write_error_log($e); //记录错误日志
            apiReturn(Error_Log);
        }
    }

    /**
     * 获取评论列表
     * @method getCommentList
     * @author chengzhigang<1256699215@qq.com>
     */
    public function getCommentList($pid = 0, $tomail)
    {
        $data = $this->ArticleCommentModel->alias('a')->join('user_info b', 'a.user_id=b.id')->cache(true, 300)->where('a.pid', $pid)->field('a.*,b.head_pic')->order('a.likes desc,a.create_time desc')->select()->toArray();
        if (count($data) == 0) {
            return $data;
        }
        foreach ($data as $k => $val) {
            $data[$k]["child"] = [];
            $data[$k]['likes'] += $this->redis->zscore('CommentLikeNum', $val['article_id'] . ":" . $val['id']);
            $data[$k]['tonickname'] = $tomail;
            $tree = $this->getCommentList($val["id"], $data[$k]['nickname']);
            if (!empty($tree)) {
                $data[$k]["child"] = $tree;
            }
        }
        return $data;
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
            $article_id = input('id');
            if (empty($user_id)) {
                apiReturn('请登录博客');
            }
            if (!is_numeric($user_id) || !$article_id || !is_numeric($article_id) || !is_numeric($pid)) {
                apiReturn('非法参数');
            }
            $info = $this->getArticleInfo($article_id);
            if ($info) {
                apiReturn('文章不存在');
            }
            //判断用户是否点过赞记录
            $result = $this->redis->hget("CommentLikeLog", $user_id . ":" . $article_id . ":" . $pid);
            if ($result) {
                //之前点过赞啦取消点赞
                $this->redis->zincrby('CommentLikeNum', -1, $article_id . ":" . $pid);
                $this->redis->zincrby('UserLikeNum', -1, $user_id);
                $this->redis->hdel("CommentLikeLog", $user_id . ":" . $article_id . ":" . $pid);
                $msg = '取消点赞';
            } else {
                //数据库查询
                $likeInfo = $this->ArticleLikeModel->where('article_id', $article_id)->where('pid', $pid)->where('user_id', $user_id)->find();
                if ($likeInfo) {
                    //之前点过赞啦取消点赞
                    $this->redis->zincrby('CommentLikeNum', -1, $article_id . ":" . $pid);
                    $this->redis->zincrby('UserLikeNum', -1, $user_id);
                    //直接更改数据库
                    $this->ArticleLikeModel->where('article_id', $article_id)->where('pid', $pid)->where('user_id', $user_id)->delete();
                    $msg = '取消点赞';
                } else {
                    //该用户未点赞
                    $this->redis->zincrby('CommentLikeNum', 1, $article_id . ":" . $pid);
                    $this->redis->zincrby('UserLikeNum', 1, $user_id);
                    //记录点赞日志
                    $this->redis->hset("CommentLikeLog", $user_id . ":" . $article_id . ":" . $pid, date('Y-m-d H:i:s'));
                    $msg = '进行点赞';
                }
            }
            $CommentLikeNum = $this->redis->zscore('CommentLikeNum', $article_id . ":" . $pid);
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
     * 文章列表页
     * @method articleList
     * @author chengzhigang<1256699215@qq.com>
     * @param
     * @return
     */
    public function articleList()
    {
        $type = input('type/d');
        $cate_id = input('cate_id');
        $filter = input('filter/s');
        $where = [];
        $where[] = ['a.show', 'eq', 2];
        if (!empty($filter)) {
            $where[] = ['a.title|c.name', 'like', '%' . trim($filter) . '%'];
          	$result = sphinxSearch('blog;blog_incre',trim($filter),1,15);
          	if($result){
            	$count = $result['count'];
            }else{
            	$count = 0;
            }
        }else{
        	if ($type == 1) {
            //普通
        } elseif ($type == 2) {
            //新
            $where[] = ['a.new', 'eq', 2];
        } elseif ($type == 3) {
            //推荐
            $where[] = ['a.recomd', 'eq', 2];
        } else {
            //分类
            if ($cate_id && is_numeric($cate_id)) {
                $where[] = ['b.cate_id', 'eq', $cate_id];
            }
        }
        $count = $this->ArticleInfoModel->alias('a')
            ->join('article_cate_relate b', 'b.article_id=a.id', 'left')
            ->join('article_cate c', 'b.cate_id=c.id')->cache(true, 300)->where($where)->field('a.*')->order('a.create_time desc')->group('a.id')->count();
        }
        $this->assign('type', $type);
        $this->assign('cate_id', $cate_id);
        $this->assign('filter', $filter);
        $this->assign('count', $count);
        return $this->fetch();
    }

    /**
     * 获取文章列表
     * @method getArticleList
     * @author chengzhigang<1256699215@qq.com>
     * @param page type id
     * @return
     */
    public function getArticleList()
    {
        try {
            $page = input('page/d', 1);
            $perpage = input('perpage/d', 15);
            $type = input('type/d');
            $cate_id = input('cate_id');
            $filter = input('filter/s');
            $count = 0;
            $list = [];
            $where = [];
            $where[] = ['a.show', 'eq', 2];
            if (!empty($filter)) {
              	$result = sphinxSearch('blog;blog_incre',trim($filter),$page,$perpage);
                if($result){
                    $count = $result['count'];
                    $ids = $result['ids'];
                    $list = $this->ArticleInfoModel->cache(true, 300)->where('id','in',$ids)->order('id desc')->select()->toArray(); 
                }
            }else{
            	 if ($type == 1) {
                //普通
                } elseif ($type == 2) {
                    //新
                    $where[] = ['a.new', 'eq', 2];
                } elseif ($type == 3) {
                    //推荐
                    $where[] = ['a.recomd', 'eq', 2];
                } else {
                    //分类
                    if ($cate_id && is_numeric($cate_id)) {
                        $where[] = ['b.cate_id', 'eq', $cate_id];
                    }
                }
                $count = $this->ArticleInfoModel->alias('a')
                    ->join('article_cate_relate b', 'b.article_id=a.id', 'left')
                    ->join('article_cate c', 'b.cate_id=c.id')->cache(true, 300)->where($where)->field('a.*')->order('a.create_time desc')->group('a.id')->count();
                $list = $this->ArticleInfoModel->alias('a')
                    ->join('article_cate_relate b', 'b.article_id=a.id', 'left')
                    ->join('article_cate c', 'b.cate_id=c.id')->cache(true, 300)->where($where)->field('a.*')->group('a.id')->page($page, $perpage)->order('a.create_time desc')->select()->toArray();
            }
            $list = $this->getNewList($list);
            apiReturn('请求成功', 0, array('data' => $list, 'count' => $count, 'page' => $page));
        } catch (\Exception $e) {
            write_error_log($e); //记录错误日志
            apiReturn(Error_Log);
        }
    }

    public function getNewList($data)
    {
        foreach ($data as &$val) {
            $hitCount = $this->redis->zscore('ArticleHitNum', $val['id']);
            $likeCount = $this->redis->zscore('ArticleLikeNum', $val['id']);
            $hateCount = $this->redis->zscore('ArticleHateNum', $val['id']);
            $commentCount = $this->redis->zscore('ArticleCommentNum', $val['id']);
            $val['hits'] += ($hitCount ? $hitCount : 0);
            $val['likes'] += ($likeCount ? $likeCount : 0);
            $val['hates'] += ($hateCount ? $hateCount : 0);
            $val['comments'] += ($commentCount ? $commentCount : 0);
        }
        return $data;
    }
}
