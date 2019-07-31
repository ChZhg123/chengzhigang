<?php
namespace app\index\controller;

use think\Controller;
use think\facade\Log;
class Base extends Controller
{
    public function initialize()
    {
        //接受参数
        $this->param = $this->request->param();
      	$this->redis = sentinelPort();
        //调用模型
        $this->UserInfoModel = model('user_info'); //用户信息表
        $this->UserActiveLogModel = model('user_active_log');//用户行为记录表
        $this->AdminUserModel = model('admin_user'); //管理员表
        $this->AdminRoleModel = model('admin_role'); //角色表
        $this->FileUploadModel = model('file_upload'); //商品表
        $this->SideNavModel = model('side_nav'); //侧边栏导航
        $this->FontAwesomeModel = model('font_awesome'); //图标表
        $this->ArticleInfoModel = model('article_info'); //文章表
        $this->ArticleCateModel = model('article_cate'); //文章类型表
        $this->ArticleCateRelateModel = model('article_cate_relate');
        $this->ArticleHistoryModel = model('article_history'); //文章浏览记录表
        $this->ArticleLikeModel = model('article_like'); //文章点赞记录表
        $this->GoodsInfoModel = model('goods_info'); //商品表
        $this->GoodsCateModel = model('goods_cate'); //商品分类表
        $this->GoodsBrandModel = model('goods_brand'); //商品品牌表
        $this->GoodsSpecInfoModel = model('goods_spec_info'); //商品规格表
        $this->GoodsSpecValueModel = model('goods_spec_value'); //商品规格值表
        $this->GoodsSkuModel = model('goods_sku'); //商品sku表
        $this->GoodsCateRelateModel = model('goods_cate_relate'); //商品分类关联表
        $this->AdminBannerModel = model('admin_banner'); //轮播图表
        $this->AdminNavModel = model('admin_nav'); //导航表
        $this->AdminAdvertModel = model('admin_advert');//广告表
        $this->ArticleCommentModel = model('article_comment');//文章评论表
        $this->AdminLinkModel = model('admin_link');//友情链接表
        $this->LeaveInfoModel = model('leave_info');//用户留言表
        $this->LeaveLikeModel = model('leave_like');//用户留言点赞表
        $this->ForumInfoModel = model('forum_info');//论坛文章表
        $this->ForumCommentModel = model('forum_comment');
        $this->ForumLikeModel = model('forum_like');
        //自动登录机制
        if(empty(session('user_id'))){
            $encrypt_pwd = cookie('cookie_id');
            $userinfo = $this->UserInfoModel->where('encrypt_pwd',$encrypt_pwd)->find();
            if(!empty($userinfo)){
                session('user_id', $userinfo['id']);
                session('nickname',$userinfo['nickname']);
                session('user_info',$userinfo);
                cookie('cookie_id',$encrypt_pwd,3600*24*30);
            }
        }
        //导航栏
        if(!json_decode($this->redis->get('HomeNav'),true)){
            $where = [];
            $where[] = ['level', 'eq', 1];
            $nav = $this->AdminNavModel->where($where)->order('sort')->select()->toArray();
            foreach ($nav as &$val) {
                $val['child'] = $this->AdminNavModel->where('pid', $val['id'])->order('sort')->select()->toArray();
            }
            $this->redis->setex('HomeNav',3600,json_encode($nav));
        }else{
            $nav = json_decode($this->redis->get('HomeNav'),true);
        }
        //广告列表
        if(!json_decode($this->redis->get('AdvertList'),true)){
            $advertList = [];
            //猜你喜欢图片
            $advertList['like'] = $this->AdminAdvertModel->where('position','like')->field('image,url,desc')->find();
             //首页中间广告
            $advertList['center'] = $this->AdminAdvertModel->where('position','homeCenter')->field('image,url,desc')->find();
            $this->redis->setex('AdvertList',3600,json_encode($advertList));
        }else{
            $advertList = json_decode($this->redis->get('AdvertList'),true);
        }
        //侧边栏
        //获取最新文章
        if(!json_decode($this->redis->get('HomeNew'),true)){
            $where = [];
            $where['show'] = 2;
            $where['new'] = 2;
            $perpage = 4;//默认展示4条
            $newlist = $this->ArticleInfoModel->where($where)->field('id,title')->page(1,$perpage)->order('create_time desc')->select()->toArray();
            $this->redis->setex('HomeNew',3600,json_encode($newlist));
        }else{
            $newlist = json_decode($this->redis->get('HomeNew'),true);
        }
        //点击排行
        if(!json_decode($this->redis->get('HomeClick'),true)){
            $where = [];
            $where['show'] = 2;
            $perpage = 10;//默认展示10条
            $clicklist = $this->ArticleInfoModel->where($where)->field('id,title')->page(1,$perpage)->order('hits desc')->select()->toArray();
            $this->redis->setex('HomeClick',3600,json_encode($clicklist));
        }else{
            $clicklist = json_decode($this->redis->get('HomeClick'),true);
        }
        //猜你喜欢
        if(empty(session('user_id'))){
            $likelist = $this->getLikeArticle();
        }else{
            if(!$this->redis->get('HomeLike:'.session('user_id'))){
                $where = [];
                $where[] = ['a.show','eq',2];
                $user_id = session('user_id');
                $perpage = 10;//默认展示10条
                //查看浏览记录
                $articleId = $this->ArticleHistoryModel->where('user_id',$user_id)->order('create_time desc')->value('article_id');
                if($articleId){
                    $cateArr = $this->ArticleCateRelateModel->where('article_id',$articleId)->column('cate_id');
                    $where[] = ['b.cate_id','in',$cateArr];
                    $likelist = $this->ArticleInfoModel->alias('a')
                                ->join('article_cate_relate b','b.article_id=a.id')
                                ->where($where)
                                ->field('a.id,a.title,a.image')->page(1,$perpage)->group('a.id')->order('a.hits desc')->select()->toArray();
                    if(count($likelist)){
                        $this->redis->setex('HomeLike:'.session('user_id'),3600,json_encode($likelist));
                    }else{
                        $likelist = $this->getLikeArticle();
                    }
                }else{
                    $likelist = $this->getLikeArticle();
                }
            }else{
                $likelist = json_decode($this->redis->get('HomeLike:'.session('user_id')),true);
            }
        }
        //标签云（取文章分类的最小分类）
        if(!json_decode($this->redis->get('HomeTabel'),true)){
            $where = [];
            $where['is_show'] = 1;
            $where['level'] = 2;
            $tabellist = $this->ArticleCateModel->where($where)->field('id,name')->order('sort asc')->select()->toArray();
            $this->redis->setex('HomeTabel',3600,json_encode($tabellist));
        }else{
            $tabellist = json_decode($this->redis->get('HomeTabel'),true);
        }
        //友情链接
        if(!json_decode($this->redis->get('HomeLink'),true)){
            $linklist = $this->AdminLinkModel->field('name,url')->order('sort asc')->select()->toArray();
            $this->redis->setex('HomeLink',3600,json_encode($linklist));
        }else{
            $linklist = json_decode($this->redis->get('HomeLink'),true);
        }
        $this->assign('advertList',$advertList);
        $this->assign('nav', $nav);
        $this->assign('newlist', $newlist);
        $this->assign('clicklist', $clicklist);
        $this->assign('likelist', $likelist);
        $this->assign('tabellist', $tabellist);
        $this->assign('linklist', $linklist);
    }

    public function getLikeArticle(){
        if(!json_decode($this->redis->get('HomeLike'),true)){
            //随机抽取
            $where = [];
            $where['show'] = 2;
            $perpage = 10;//默认展示10条
            $likelist = $this->ArticleInfoModel->where($where)->field('id,title,image')->page(1,$perpage)->orderRaw('rand()')->select()->toArray();
            $this->redis->setex('HomeLike',3600,json_encode($likelist));
        }else{
            $likelist = json_decode($this->redis->get('HomeLike'),true);
        }
        return $likelist;
    }

    /**
     * 获取文章内容
     * @method getArticleInfo
     * @author chengzhigang<1256699215@qq.com>
     * @param $id
     * @return 
     */
    public function getArticleInfo($id){
        if($id&&is_numeric($id)){
            if(!$this->redis->get('ArticleInfo_'.$id)){
                $info = $this->ArticleInfoModel->where('id',$id)->find();
                if($info){
                    $condition = [];
                    $condition[] = ['show','eq',2];
                    $condition[] = ['id','lt',$id];
                    $info['prev'] = $this->ArticleInfoModel->where($condition)->field('id,title')->order('id desc')->limit(1)->find();
                    $where = [];
                    $where[] = ['show','eq',2];
                    $where[] = ['id','lt',$id];
                    $info['next'] = $this->ArticleInfoModel->where($where)->field('id,title')->order('id asc')->limit(1)->find();
                    //获取文章分类
                    $info['cate'] = $this-> ArticleCateRelateModel -> alias('a') -> join('article_cate b','a.cate_id=b.id')->where('a.article_id',$id)->field('b.id,b.name')->order('b.level')->select()->toArray();
                    //获取文章分类
                    $info['comment'] = [];
                    $newCom = [];
                    $comment = $this ->ArticleCommentModel -> where('article_id',$id) -> order('likes desc,create_time desc')->select()->toArray();
                    foreach($comment as $v){
                        $newCom[$v['id']] = $v;
                    }
                    $info['comment'] = $newCom;
                    $this->redis->setex('ArticleInfo_'.$id,3600,json_encode($info));
                }else{
                    return false;
                }
            }else{
                $info = json_decode($this->redis->get('ArticleInfo_'.$id),true);
            }
        }else{
            return $this->error(Error_404);
        }
        $hitCount = $this->redis->zscore('ArticleHitNum',$id);
        $likeCount = $this->redis->zscore('ArticleLikeNum',$id);
        $hateCount = $this->redis->zscore('ArticleHateNum',$id);
        $commentCount = $this->redis->zscore('ArticleCommentNum',$id);
        $info['hits'] += ($hitCount?$hitCount:0);
        $info['likes'] += ($likeCount?$likeCount:0);
        $info['hates'] += ($hateCount?$hateCount:0);
        $info['comments'] += ($commentCount?$commentCount:0);
        return $info;
    }

    public function getForumInfo($id){
        if($id&&is_numeric($id)){
            if(!$this->redis->get('ForumInfo_'.$id)){
                $info = $this->ForumInfoModel->alias('a')->join('user_info b','a.user_id=b.id')->where('a.id',$id)->field('a.*,b.nickname,b.head_pic')->find();
                if($info){
                    $condition = [];
                    $condition[] = ['check_status','eq',2];
                    $condition[] = ['id','lt',$id];
                    $where = [];
                    $where[] = ['check_status','eq',2];
                    $where[] = ['id','gt',$id];
                    $info['prev'] = $this->ForumInfoModel->where($condition)->field('id,title')->order('id desc')->limit(1)->find();
                    $info['next'] = $this->ForumInfoModel->where($where)->field('id,title')->order('id asc')->limit(1)->find();
                    $newCom = [];
                    $comment = $this ->ForumCommentModel -> where('forum_id',$id) -> order('likes desc,create_time desc')->select()->toArray();
                    foreach($comment as $v){
                        $newCom[$v['id']] = $v;
                    }
                    $info['comment'] = $newCom;
                    $this->redis->setex('ForumInfo_'.$id,3600,json_encode($info));
                }else{
                    return false;
                }
            }else{
                $info = json_decode($this->redis->get('ForumInfo_'.$id),true);
            }
        }else{
            return $this->error(Error_404);
        }
        $hitCount = $this->redis->zscore('ForumHitNum',$id);
        $likeCount = $this->redis->zscore('ForumLikeNum',$id);
        $commentCount = $this->redis->zscore('ForumCommentNum',$id);
        $info['hits'] += ($hitCount?$hitCount:0);
        $info['likes'] += ($likeCount?$likeCount:0);
        $info['comments'] += ($commentCount?$commentCount:0);
        return $info;
    }

    /**
     * 记录用户行为日志
     * @param [type] $content
     * @method writeUserActiveLog
     * @author chengzhigang<1256699215@qq.com>
     * @param 
     * @return 
     */
    public function writeUserActiveLog($content){
        $data = [];
        $data['user_id'] = session('user_id');
        $data['nickname'] = session('nickname');
        $data['content'] = $content;
        $data['create_time'] = date('Y-m-d H:i:s');
        if(is_numeric(session('user_id'))){
            $this->redis->rpush('UserActiveLog_'.$data['user_id'],json_encode($data));
        }
    }

    public function getAllForumList(){
        $keys = $this->redis->keys('HomeForumList*');
        $this->redis->del($keys);
        $list = $this->ForumInfoModel->alias('a')->join('user_info b','a.user_id=b.id')->where('a.check_status',2)->field('a.*,b.nickname,b.head_pic')->select()->toArray();
        foreach($list as $val){
            if($val['stick']==1){
                //综合（按点赞评论数排序）
                $score = floatval($val['hits'])+floatval($val['likes']);
                $min = strtotime(date('Y-m-d'));
                $time = strtotime($val['create_time'])-$min;
                $this->redis->zAdd('HomeForumList_1',$score,$val['id']);
                //分享
                if($val['type']==1){
                    $this->redis->zAdd('HomeForumList_2',$time,$val['id']);
                }
                //讨论
                if($val['type']==2){
                    $this->redis->zAdd('HomeForumList_3',$time,$val['id']);
                }
                //最新
                $this->redis->zAdd('HomeForumList_4',$time,$val['id']);
                //推荐
                if($val['recomd']==2){
                    $this->redis->zAdd('HomeForumList_5',$time,$val['id']);
                }
            }
            //hash缓存（数据库数据）
            $this->redis->hSet('HomeForumList',$val['id'],json_encode($val));
        }
    }

     /**
     * 定时任务处理数据
     * @method TimingRedis
     * @author chengzhigang<1256699215@qq.com>
     */
    public function TimingRedis()
    {
        try {
            //文章点击量新增
            $hitCount = $this->redis->llen('ArticleHits');
            for($i=0;$i<$hitCount;$i++){
                $hitData = $this->redis->lpop('ArticleHits');
                if(!empty($hitData)){
                    $hitData = json_decode($hitData,true);
                    //记录浏览日志
                    write_article_hit_log($hitData['article_id'],$hitData['user_id'],$hitData['time']);
                }
            }
            //更新文章点击量
            $articleHitArr = $this->redis->zrange('ArticleHitNum',0,-1);
            if($articleHitArr){
                foreach($articleHitArr as $v){
                    if($v&&is_numeric($v)){
                        $info = $this -> getArticleInfo($v);
                        if($info){
                            $articleData = [];
                            $articleData['hits'] = intval($info['hits']);
                            $this->ArticleInfoModel->updateData(array('id'=>$info['id']),$articleData);
                        }
                    }
                    //删除redis数据
                    $this->redis->zRem('ArticleHitNum',$v);
                    //清除缓存
                    $this->redis->set('ArticleInfo_'.$v,null);  
                }
            }
            //更新论坛点击量
            $forumHitArr = $this->redis->zrange('ForumHitNum',0,-1);
            if($forumHitArr){
                foreach($forumHitArr as $v){
                    if($v&&is_numeric($v)){
                        $info = $this -> getForumInfo($v);
                        if($info){
                            $forumData = [];
                            $forumData['hits'] = intval($info['hits']);
                            $this->ForumInfoModel->updateData(array('id'=>$info['id']),$forumData);
                        }
                    }
                    //删除redis数据
                    $this->redis->zRem('ForumHitNum',$v);
                    //清除缓存
                    $this->redis->set('ForumInfo_'.$v,null);  
                }
            }
            //文章点赞踩记录
            $likelist = $this -> redis -> hgetall('ArticleLikeHateLog');
            foreach($likelist as $k=>$v){
                $karr = explode(':',$k);
                $likeData = [];
                $likeData['user_id'] = $karr[0];
                $likeData['article_id'] = $karr[1];
                $varr = json_decode($v,true);
                $likeData['type'] = $varr['type'];
                $likeData['update_time'] = $varr['time'];
                //记录点赞日志
                $likeLog = $this->ArticleLikeModel->where('pid',0)->where('article_id',$likeData['article_id'])->where('user_id',$likeData['user_id'])->find();
                if($likeLog){
                    //更新
                    $this->ArticleLikeModel->where('id',$likeLog['id'])->update($likeData);
                }else{
                    //新增
                    $likeData['create_time'] = $varr['time'];
                    write_article_like_log($likeData);
                }
                //删除redis数据
                $this->redis->hdel("ArticleLikeHateLog",$likeData['user_id'].":".$likeData['article_id']);
            }  
            //论坛文章点赞记录
            $likelist = $this -> redis -> hgetall('ForumLikeLog');
            foreach($likelist as $k=>$v){
                $karr = explode(':',$k);
                $likeData = [];
                $likeData['user_id'] = $karr[0];
                $likeData['forum_id'] = $karr[1];
                $likeData['update_time'] = $v;
                //记录点赞日志
                $likeLog = $this->ForumLikeModel->where('pid',0)->where('forum_id',$likeData['forum_id'])->where('user_id',$likeData['user_id'])->find();
                if($likeLog){
                    //更新
                    $this->ForumLikeModel->where('id',$likeLog['id'])->update($likeData);
                }else{
                    //新增
                    $likeData['create_time'] = $v;
                    $this->ForumLikeModel->createData($likeData);
                }
                //删除redis数据
                $this->redis->hdel("ForumLikeLog",$likeData['user_id'].":".$likeData['forum_id']);
            } 
            //更新论坛文章点赞数量
            $forumLikeArr = $this->redis->zrange('ForumLikeNum',0,-1);
            if($forumLikeArr){
                foreach($forumLikeArr as $v){
                    //更新点赞数量
                    if($v&&is_numeric($v)){
                        $info = $this -> getForumInfo($v);
                        if($info){
                            $forumData = [];
                            $forumData['likes'] = intval($info['likes']);
                            $this->ForumInfoModel->updateData(array('id'=>$info['id']),$forumData);
                        }
                    }
                    //删除redis数据
                    $this->redis->zRem('ForumLikeNum',$v);
                    //清除缓存
                    $this->redis->set('ForumInfo_'.$v,null);  
                }
            }
            //更新点赞数量
            $articleLikeArr = $this->redis->zrange('ArticleLikeNum',0,-1);
            if($articleLikeArr){
                foreach($articleLikeArr as $v){
                    //更新点赞数量
                    if($v&&is_numeric($v)){
                        $info = $this -> getArticleInfo($v);
                        if($info){
                            $articleData = [];
                            $articleData['likes'] = intval($info['likes']);
                            $this->ArticleInfoModel->updateData(array('id'=>$info['id']),$articleData);
                        }
                    }
                    //删除redis数据
                    $this->redis->zRem('ArticleLikeNum',$v);
                    //清除缓存
                    $this->redis->set('ArticleInfo_'.$v,null);  
                }
            }
            //更新点踩数量
            $articleHateArr = $this->redis->zrange('ArticleHateNum',0,-1);
            if($articleHateArr){
                foreach($articleHateArr as $v){
                    //更新点踩数量
                    if($v&&is_numeric($v)){
                        $info = $this -> getArticleInfo($v);
                        if($info){
                            $articleData = [];
                            $articleData['hates'] = intval($info['hates']);
                            $this->ArticleInfoModel->updateData(array('id'=>$info['id']),$articleData);
                        }
                    }
                    //删除redis数据
                    $this->redis->zRem('ArticleHateNum',$v);
                    //清除缓存
                    $this->redis->set('ArticleInfo_'.$v,null);  
                }
            }
            //文章评论点赞记录
            $likelist = $this -> redis -> hgetall('CommentLikeLog');
            foreach($likelist as $k=>$v){
                $karr = explode(':',$k);
                $likeData = [];
                $likeData['user_id'] = $karr[0];
                $likeData['article_id'] = $karr[1];
                $likeData['pid'] = $karr[2];
                $likeData['update_time'] = $v;
                $likeData['type'] = 3;
                //记录点赞日志
                $likeLog = $this->ArticleLikeModel->where('pid',$likeData['pid'])->where('article_id',$likeData['article_id'])->where('user_id',$likeData['user_id'])->find();
                if($likeLog){
                    //更新
                    $this->ArticleLikeModel->where('id',$likeLog['id'])->update($likeData);
                }else{
                    //新增
                    $likeData['create_time'] = $v;
                    write_article_like_log($likeData);
                }
                //删除redis数据
                $this->redis->hdel("CommentLikeLog",$likeData['user_id'].":".$likeData['article_id'].":".$likeData['pid']);
            }  
            //论坛文章评论点赞记录
            $likelist = $this -> redis -> hgetall('ForumCommentLikeLog');
            foreach($likelist as $k=>$v){
                $karr = explode(':',$k);
                $likeData = [];
                $likeData['user_id'] = $karr[0];
                $likeData['forum_id'] = $karr[1];
                $likeData['pid'] = $karr[2];
                $likeData['update_time'] = $v;
                //记录点赞日志
                $likeLog = $this->ForumLikeModel->where('pid',$likeData['pid'])->where('forum_id',$likeData['forum_id'])->where('user_id',$likeData['user_id'])->find();
                if($likeLog){
                    //更新
                    $this->ForumLikeModel->where('id',$likeLog['id'])->update($likeData);
                }else{
                    //新增
                    $likeData['create_time'] = $v;
                    $this->ForumLikeModel->createData($likeData);
                }
                //删除redis数据
                $this->redis->hdel("ForumCommentLikeLog",$likeData['user_id'].":".$likeData['forum_id'].":".$likeData['pid']);
            }  
            //文章评论数量
            $articleCommentArr = $this->redis->zrange('ArticleCommentNum',0,-1);
            if($articleCommentArr){
                foreach($articleCommentArr as $v){
                    //更新点赞数量
                    if($v&&is_numeric($v)){
                        $info = $this -> getArticleInfo($v);
                        if($info){
                            $articleData = [];
                            $articleData['comments'] = intval($info['comments']);
                            $this->ArticleInfoModel->updateData(array('id'=>$info['id']),$articleData);
                        }
                    }
                     //删除redis数据
                     $this->redis->zRem('ArticleCommentNum',$v);
                     //清除缓存
                     $this->redis->set('ArticleInfo_'.$v,null);  
                }
            }
             //论坛文章评论数量
             $forumCommentArr = $this->redis->zrange('ForumCommentNum',0,-1);
             if($forumCommentArr){
                 foreach($forumCommentArr as $v){
                     //更新点赞数量
                     if($v&&is_numeric($v)){
                         $info = $this -> getForumInfo($v);
                         if($info){
                            $forumData = [];
                            $forumData['comments'] = intval($info['comments']);
                            $this->ForumInfoModel->updateData(array('id'=>$info['id']),$forumData);
                         }
                     }
                      //删除redis数据
                      $this->redis->zRem('ForumCommentNum',$v);
                      //清除缓存
                      $this->redis->set('ForumInfo_'.$v,null);  
                 }
             }
            //文章评论点赞数量
            $CommentLikeArr = $this->redis->zrange('CommentLikeNum',0,-1);
            if($CommentLikeArr){
                foreach($CommentLikeArr as $v){
                    //更新点赞数量
                    $varr = explode(":",$v);
                    $article_id = $varr[0];
                    $pid = $varr[1];
                    $info = $this -> getArticleInfo($article_id);
                    if($info&&isset($info['comment'][$pid])){
                        $likeInfo = $info['comment'][$pid];
                        $likeCount = $this->redis->zscore('CommentLikeNum',$v);
                        $articleData = [];
                        $articleData['likes'] = intval($likeInfo['likes'])+intval($likeCount);
                        $this->ArticleCommentModel->updateData(array('id'=>$pid),$articleData);
                    }
                    //删除redis数据
                    $this->redis->zRem('CommentLikeNum',$v);
                    //清除缓存
                    $this->redis->set('ArticleInfo_'.$article_id,null);  
                }
            }
            //论坛文章评论点赞数量
            $CommentLikeArr = $this->redis->zrange('ForumCommentLikeNum',0,-1);
            if($CommentLikeArr){
                foreach($CommentLikeArr as $v){
                    //更新点赞数量
                    $varr = explode(":",$v);
                    $forum_id = $varr[0];
                    $pid = $varr[1];
                  	if($forum_id&&is_numeric($forum_id)){
                      $info = $this -> getForumInfo($forum_id);
                      if($info&&isset($info['comment'][$pid])){
                          $likeInfo = $info['comment'][$pid];
                          $likeCount = $this->redis->zscore('ForumCommentLikeNum',$v);
                          $forumData = [];
                          $forumData['likes'] = intval($likeInfo['likes'])+intval($likeCount);
                          $this->ForumCommentModel->updateData(array('id'=>$pid),$forumData);
                      }
                    }
                    //删除redis数据
                    $this->redis->zRem('ForumCommentLikeNum',$v);
                    //清除缓存
                    $this->redis->set('ForumInfo_'.$forum_id,null);  
                }
            }
            //留言点赞数量
            $CommentLikeArr = $this->redis->zrange('LeaveLikeNum',0,-1);
            if($CommentLikeArr){
                foreach($CommentLikeArr as $v){
                    //更新点赞数量
                    $CommentInfo = $this->redis->hget('leaveList',$v);
                    if(empty($CommentInfo)){
                        $CommentInfo = $this->LeaveInfoModel->where('id',$v)->find();
                    }else{
                      	$CommentInfo = json_decode($CommentInfo,true);
                    }
                    if($CommentInfo){
                        $likeCount = $this->redis->zscore('LeaveLikeNum',$v);
                        $leaveData = [];
                        $leaveData['likes'] = intval($CommentInfo['likes'])+intval($likeCount);
                        $this->LeaveInfoModel->updateData(array('id'=>$v),$leaveData);
                    }
                    //删除redis数据
                    $this->redis->zRem('LeaveLikeNum',$v);
                    $this->redis->del('leaveList');
                }
            }
            //留言点赞记录
            $leaveLikeList = $this -> redis -> hgetall('LeaveLikeLog');
            foreach($leaveLikeList as $k=>$v){
                $karr = explode(':',$k);
                $likeData = [];
                $likeData['user_id'] = $karr[0];
                $likeData['leave_id'] = $karr[1];
                $likeData['update_time'] = $v;
                //记录点赞日志
                $likeLog = $this->LeaveLikeModel->where('leave_id',$likeData['leave_id'])->where('user_id',$likeData['user_id'])->find();
                if($likeLog){
                    //更新
                    $this->LeaveLikeModel->where('id',$likeLog['id'])->update($likeData);
                }else{
                    //新增
                    $likeData['create_time'] = $v;
                    $this->LeaveLikeModel->createData($likeData);
                }
                //删除redis数据
                $this->redis->hdel("LeaveLikeLog",$likeData['user_id'].":".$likeData['leave_id']);
            } 
            $this->getAllForumList(); //每天执行一次
            $this->redis->del('leaveList');//删除评论列表
            Log::info('定时清理redis缓存');
        } catch (\Exception $e) {
            write_error_log($e); //记录错误日志
            apiReturn(Error_Log);
        }

    }
}
