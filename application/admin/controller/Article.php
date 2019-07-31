<?php
/**
 * 文章管理
 * @author chengzhigang<1256699215@qq.com>
 */
namespace app\admin\controller;
use think\Db;
class Article extends Base
{
    /**
     * 文章列表页面渲染
     * @method articlelist
     * @author chengzhigang<1256699215@qq.com>
     */
    public function articlelist()
    {
        //列表路径存cookie
        Cookie('Article/articlelist', request()->url());
        $filter = input('filter/s');
        $where = [];
        if (!empty($filter)) {
            $where[] = array('title', 'like', '%' . trim($filter) . '%');
        }
        $list = $this->ArticleInfoModel->where($where)->order('create_time desc')->paginate(Page_Num);
        $this->assign('list', $list);
        $this->assign('filter', $filter);
        return $this->fetch();
    }

    /**
     * 获取文章分类
     * @method getArticleCate
     * @author chengzhigang<1256699215@qq.com>
     */
    public function getArticleCate()
    {
        try {
            $pid = input('pid');
            $data = $this->ArticleCateModel->where('pid', $pid)->field('id,name')->select();
            apiReturn('请求成功', 0, $data);
        } catch (\Exception $e) {
            write_error_log($e); //记录错误日志
            apiReturn(Error_Log);
        }
    }

    /**
     * 新增文章
     * @method addArticle
     * @author chengzhigang<1256699215@qq.com>
     */
    public function addArticle(){
        if(request()->isGet()){
            $category = $this->ArticleCateModel->where('level',1)->field('id,name')->select();
            $this->assign('category',$category);
            return $this->fetch();
        }else{
            try{
                $data = input('post.');
                //验证字段
                if(empty($data['title'])){
                    apiReturn('文章标题不能为空');
                }
                if(empty($data['author'])){
                    apiReturn('文章作者不能为空');
                }
                if(empty($data['excerpt'])){
                    apiReturn('文章摘要不能为空');
                }
                //分类必填
                if (!isset($data['category']) || empty($data['category'])) {
                    apiReturn('请选择文章类型');
                }
                Db::startTrans();
                if (isset($data['show'])) {
                    $data['show'] = 2;
                }else{
                    $data['show'] = 1;
                }
                if (isset($data['recomd'])) {
                    $data['recomd'] = 2;
                }
                if (isset($data['new'])) {
                    $data['new'] = 2;
                }
                if (isset($data['stick'])) {
                    $data['stick'] = 2;
                }
                //图片上传
                if(request()->file('image')){
                    $filearr = upload(request()->file('image'));
                    if($filearr['status']==1){
                        apiReturn($filearr['msg']);
                    }else{
                        $data['image'] = $filearr['data']['path'];
                    }
                }
                $res = $this -> ArticleInfoModel->createData($data);
                //新增成功
                $article_id = $this->ArticleInfoModel->id;
                //新增商品分类关联
                foreach ($data['category'] as $v) {
                    $relate = [];
                    $relate['article_id'] = $article_id;
                    $relate['cate_id'] = $v;
                    $this->ArticleCateRelateModel->createData($relate);
                }
                $this->redis->set('Homecate',null);
                Db::commit();
            }catch(\Exception $e){
                Db::rollback();
                write_error_log($e); //记录错误日志
                apiReturn(Error_Log);
            }
            apiReturn('新增成功', 0, array('url' => Cookie('Article/articlelist')));
        }
    }

    /**
     * 编辑文章
     * @method editArticle
     * @author chengzhigang<1256699215@qq.com>
     */
    public function editArticle(){
        if(request()->isGet()){
            $id = input('id');
            $info = $this -> ArticleInfoModel -> where('id',$id)->find();
            $category = $this->ArticleCateModel->where('level',1)->field('id,name')->select();
            $this->assign('category',$category);
            $catelist = $this->ArticleCateRelateModel->alias('a')->join('article_cate b', 'a.cate_id=b.id')->where('a.article_id', $id)->field('b.id,b.name')->select();
            $this->assign('catelist', $catelist);
            $this -> assign('info',$info);
            return $this->fetch();
        }else{
            try{
                $data = input('post.');
                //验证字段
                if(empty($data['title'])){
                    apiReturn('文章标题不能为空');
                }
                if(empty($data['author'])){
                    apiReturn('文章作者不能为空');
                }
                if(empty($data['excerpt'])){
                    apiReturn('文章摘要不能为空');
                }
                //分类必填
                if (!isset($data['category']) || empty($data['category'])) {
                    apiReturn('请选择文章类型');
                }
                $category = $data['category'];
                Db::startTrans();
                if (isset($data['show'])) {
                    $data['show'] = 2;
                }else{
                    $data['show'] = 1;
                }
                if (isset($data['recomd'])) {
                    $data['recomd'] = 2;
                }
                if (isset($data['new'])) {
                    $data['new'] = 2;
                }
                if (isset($data['stick'])) {
                    $data['stick'] = 2;
                }
                //图片上传
                if(request()->file('image')){
                    $filearr = upload(request()->file('image'));
                    if($filearr['status']==1){
                        apiReturn($filearr['msg']);
                    }else{
                        $data['image'] = $filearr['data']['path'];
                    }
                }
                $article_id = $data['id'];
                $res = $this -> ArticleInfoModel->updateData(array('id'=>$data['id']),$data);
                //编辑成功
                //新增文章分类关联
                $articleCate = $this->ArticleCateRelateModel->where('article_id', $article_id)->column('cate_id');
                foreach($articleCate as $v){
                    if(!in_array($v,$category)){
                        $this->ArticleCateRelateModel->where('article_id', $article_id)->where('cate_id', $v)->delete();
                    }
                }
                foreach ($category as $v) {
                    $relate = $this->ArticleCateRelateModel->where('article_id', $article_id)->where('cate_id', $v)->find();
                    if (empty($relate)) {
                        $relateData = [];
                        $relateData['article_id'] = $article_id;
                        $relateData['cate_id'] = $v;
                        $this->ArticleCateRelateModel->createData($relateData);
                    }
                }
                $this->redis->set('Homecate',null);
                $this->redis->set('ArticleInfo_'.$data['id'],null);
                Db::commit();
                apiReturn('编辑成功', 0, array('url' => Cookie('Article/articlelist')));
            }catch(\Exception $e){
                Db::rollback();
                write_error_log($e); //记录错误日志
                apiReturn(Error_Log);
            }
        }
    }

    /**
     * 删除文章
     * @method 
     * @author chengzhigang<1256699215@qq.com>
     */
    public function delArticle(){
        try {
            $id = input('id/d');
            $res = $this->ArticleInfoModel->where('id', $id)->delete();
            //ToDo

            if ($res) {
                $this->redis->set('Homecate',null);
                apiReturn('删除成功', 0, array('url' => Cookie('Article/articlelist')));
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
     * @method changeArticle
     * @author chengzhigang<1256699215@qq.com>
     */
    public function changeArticle(){
        try{
            $id = input('id');
            $field = input('field/s');
            $value = input('value');
            $res = $this->ArticleInfoModel->updateData(array('id'=>$id),array($field=>$value));
            if($res){
                if($field=='stick'){
                    $this->redis->set('Homestick',null);
                }
                if($field=='recomd'){
                    $this->redis->set('HomeRecomd',null);
                }
                apiReturn('更改成功', 0, array('url' => Cookie('Article/articlelist')));
            }else{
                apiReturn('更改失败');
            }
        }catch(\Exception $e){
            write_error_log($e); //记录错误日志
            apiReturn(Error_Log);
        }
    }

    /**
     * 文章类型列表页面渲染
     * @method categorylist
     * @author chengzhigang<1256699215@qq.com>
     */
    public function categorylist()
    {
        //列表路径存cookie
        Cookie('Article/categorylist', request()->url());
        $filter = input('filter/s');
        $where = [];
        $where[] = ['level','eq',1];
        if (!empty($filter)) {
            $where[] = array('name', 'like', '%' . trim($filter) . '%');
        }
        $list = $this->ArticleCateModel->where($where)->order('create_time desc')->select();
        foreach ($list as &$val) {
            $val['child'] = $this->ArticleCateModel->where('pid', $val['id'])->order('create_time desc')->select();
        }
        $this->assign('list', $list);
        $this->assign('filter', $filter);
        return $this->fetch();
    }

    /**
     * 新增文章类型
     * @method addCategory
     * @author chengzhigang<1256699215@qq.com>
     */
    public function addCategory(){
        if(request()->isGet()){
            $category = $this->ArticleCateModel->where('level',1)->field('id,name')->select();
            $this->assign('category',$category);
            return $this -> fetch();
        }else{
            try{
                $data = input('post.');
                if(empty($data['name'])){
                    apiReturn('请输入文章类型');
                }
                if($data['pid']){
                    $data['level'] = 2;
                }else{
                    $data['level'] = 1;
                }
                $res = $this -> ArticleCateModel->createData($data);
                if($res){
                    $this->redis->set('Homecate',null);
                    $this->redis->set('HomeTabel',null);
                    apiReturn('新增成功', 0, array('url' => Cookie('Article/categorylist')));
                }else{
                    apiReturn('新增失败');
                }
            }catch(\Exception $e){
                write_error_log($e); //记录错误日志
                apiReturn(Error_Log);
            }
        }
    }

    /**
     * 编辑文章类型
     * @method editCategory
     * @author chengzhigang<1256699215@qq.com>
     */
    public function editCategory(){
        if(request()->isGet()){
            $id = input('id');
            $category = $this->ArticleCateModel->where('level',1)->field('id,name')->select();
            $this->assign('category',$category);
            $info = $this -> ArticleCateModel->where('id',$id)->find();
            $this->assign('info',$info);
            return $this -> fetch();
        }else{
            try{
                $id = input('id');
                $data = input('post.');
                if(empty($data['name'])){
                    apiReturn('请输入文章类型');
                }
                if($data['pid']){
                    $data['level'] = 2;
                }else{
                    $data['level'] = 1;
                }
                $res = $this -> ArticleCateModel->updateData(array('id'=>$id),$data);
                if($res){
                    $this->redis->set('Homecate',null);
                    $this->redis->set('HomeTabel',null);
                    apiReturn('编辑成功', 0, array('url' => Cookie('Article/categorylist')));
                }else{
                    apiReturn('编辑失败');
                }
            }catch(\Exception $e){
                write_error_log($e); //记录错误日志
                apiReturn(Error_Log);
            }
        }
    }

    /**
     * 删除文章类型
     * @method delCategory
     * @author chengzhigang<1256699215@qq.com>
     */
    public function delCategory(){
        try {
            Db::startTrans();
            $id = input('id/d');
            $res = $this->ArticleCateModel->where('id', $id)->delete();
            //取消绑定所有文章
            $where = [];
            $where[] = ['','exp',Db::raw("find_in_set(' . $id . ',category)")];
            $articlelist = $this->ArticleInfoModel->where($where)->field('id,category,title')->select();
            foreach($articlelist as $val){
                $category = $val['category'];
                if(count($category)==1){
                    throw new \Exception($val['title'].'只有当前分类，不能删除');
                }else{
                    foreach($category as $k=> $v){
                        if($v==$id){
                            unset($category[$k]);
                        }
                    }
                    $newData['category'] = implode(',',$category);
                    $this->ArticleInfoModel -> where('id',$val['id'])->updateData($newData);
                }
            }
            $this->redis->set('Homecate',null);
            $this->redis->set('HomeTabel',null);
            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            write_error_log($e); //记录错误日志
            apiReturn($e->getMessage());
        }
        apiReturn('删除成功', 0, array('url' => Cookie('Article/categorylist')));
    }

}