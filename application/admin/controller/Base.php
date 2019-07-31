<?php
namespace app\admin\controller;

use think\Controller;
use think\facade\Session;
class Base extends Controller
{
    public function initialize()
    {
        //接受参数
        $this->param = $this->request->param();
        //连接redis
        $this->redis = sentinelPort();
        //调用模型
        $this->UserInfoModel = model('user_info'); //用户信息表
        $this->UserActiveLogModel = model('user_active_log');//用户行为记录表
        $this->AdminUserModel = model('admin_user'); //管理员表
        $this->AdminRoleModel = model('admin_role');//角色表
        $this->FileUploadModel = model('file_upload');//商品表
        $this->SideNavModel = model('side_nav'); //侧边栏导航
        $this->FontAwesomeModel = model('font_awesome'); //图标表
        $this->ArticleInfoModel = model('article_info'); //文章表
        $this->ArticleCateModel = model('article_cate');//文章类型表
        $this->ArticleCateRelateModel = model('article_cate_relate');
        $this->GoodsInfoModel = model('goods_info');//商品表
        $this->GoodsCateModel = model('goods_cate');//商品分类表
        $this->GoodsBrandModel = model('goods_brand');//商品品牌表
        $this->GoodsSpecInfoModel = model('goods_spec_info');//商品规格表
        $this->GoodsSpecValueModel = model('goods_spec_value');//商品规格值表
        $this->GoodsSkuModel = model('goods_sku');//商品sku表
        $this->GoodsCateRelateModel = model('goods_cate_relate');//商品分类关联表
        $this->AdminBannerModel = model('admin_banner');//轮播图表
        $this->AdminNavModel = model('admin_nav');//导航表
        $this->AdminAreaModel = model('admin_area');//行政区域表2019
        $this->AdminAdvertModel = model('admin_advert');//广告表
        $this->AdminLinkModel = model('admin_link');//友情链接表
        $this->LeaveInfoModel = model('leave_info');//用户留言表
        $this->LeaveLikeModel = model('leave_like');//用户留言点赞表
        $this->ForumInfoModel = model('forum_info');//论坛文章表
        //自动登录
        if(empty(session('admin_id'))){
            $encrypt_pwd = cookie('cookie_admin_id');
            $admininfo = $this->AdminUserModel->where('encrypt_pwd',$encrypt_pwd)->find();
            if(!empty($admininfo)){
                $navs = get_side_navs($admininfo['id']);
                session('side_navs', $navs);
                session('admin_id', $admininfo['id']);
                session('admin_info', $admininfo);
                cookie('cookie_admin_id',$encrypt_pwd,3600*24*30);
            }
        }
    }
}
