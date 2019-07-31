<?php
/**
 * 广告管理
 * @author chengzhigang<1256699215@qq.com>
 */
namespace app\admin\controller;

class Advert extends Base
{
    /**
     * 轮播图列表
     * @method bannerlist
     * @author chengzhigang<1256699215@qq.com>
     */
    public function bannerlist()
    {
        //列表路径存cookie
        Cookie('Advert/bannerlist', request()->url());
        $filter = input('filter/s');
        $where = [];
        if (!empty($filter)) {
            $where[] = array('title', 'like', '%' . trim($filter) . '%');
        }
        $list = $this->AdminBannerModel->where($where)->order('create_time desc')->paginate(Page_Num);
        $this->assign('list', $list);
        $this->assign('filter', $filter);
        return $this->fetch();
    }

    /**
     * 新增轮播图
     * @method addBanner
     * @author chengzhigang<1256699215@qq.com>
     */
    public function addBanner()
    {
        if (request()->isGet()) {
            return $this->fetch();
        } else {
            try {
                $data = input('post.');
                if (empty($data['name'])) {
                    apiReturn('轮播标题不能为空');
                }
                if (request()->file('image')) {
                    $filearr = upload(request()->file('image'));
                    if ($filearr['status'] == 1) {
                        apiReturn($filearr['msg']);
                    } else {
                        $data['image'] = $filearr['data']['path'];
                    }
                }
                $res = $this->AdminBannerModel->createData($data);
                if ($res) {
                    $this->redis->set('Homebanner',null);
                    apiReturn('新增成功', 0, array('url' => Cookie('Advert/bannerlist')));
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
     * 编辑轮播图
     * @method editBanner
     * @author chengzhigang<1256699215@qq.com>
     */
    public function editBanner()
    {
        if (request()->isGet()) {
            $id = input('id');
            $info = $this->AdminBannerModel->where('id', $id)->find();
            $this->assign('info', $info);
            return $this->fetch();
        } else {
            try {
                $data = input('post.');
                if (empty($data['name'])) {
                    apiReturn('轮播标题不能为空');
                }
                if (request()->file('image')) {
                    $filearr = upload(request()->file('image'));
                    if ($filearr['status'] == 1) {
                        apiReturn($filearr['msg']);
                    } else {
                        $data['image'] = $filearr['data']['path'];
                    }
                }
                $res = $this->AdminBannerModel->updateData(array('id' => $data['id']), $data);
                if ($res) {
                    $this->redis->set('Homebanner',null);
                    apiReturn('编辑成功', 0, array('url' => Cookie('Advert/bannerlist')));
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
     * 删除轮播
     * @method delBanner
     * @author chengzhigang<1256699215@qq.com>
     * @param id
     */
    public function delBanner()
    {
        try {
            $id = input('id');
            $res = $this->AdminBannerModel->where('id', $id)->delete();
            if ($res) {
                $this->redis->set('Homebanner',null);
                apiReturn('删除成功', 0, array('url' => Cookie('Advert/bannerlist')));
            } else {
                apiReturn('删除失败');
            }
        } catch (\Exception $e) {
            write_error_log($e); //记录错误日志
            apiReturn(Error_Log);
        }
    }

    /**
     * 修改排序
     * @method changeBannerSort
     * @author chengzhigang<1256699215@qq.com>
     */
    public function changeBannerSort()
    {
        try {
            $id = input('id');
            $field = input('field');
            $value = input('value');
            $res = $this->AdminBannerModel->updateData(array('id' => $id), array($field => $value));
            if ($res) {
                apiReturn('修改成功', 0, array('url' => Cookie('Advert/bannerlist')));
            } else {
                apiReturn('修改失败');
            }
        } catch (\Exception $e) {
            write_error_log($e); //记录错误日志
            apiReturn(Error_Log);
        }
    }

    /**
     * 导航列表
     * @method navlist
     * @author chengzhigang<1256699215@qq.com>
     */
    public function navlist()
    {
        //列表路径存cookie
        Cookie('Advert/navlist', request()->url());
        $filter = input('filter/s');
        $where = [];
        $where[] = ['level', 'eq', 1];
        if (!empty($filter)) {
            $where[] = array('title', 'like', '%' . trim($filter) . '%');
        }
        $list = $this->AdminNavModel->where($where)->order('sort')->select();
        foreach ($list as &$val) {
            $val['child'] = $this->AdminNavModel->where('pid', $val['id'])->order('sort')->select();
        }
        $this->assign('list', $list);
        $this->assign('filter', $filter);
        return $this->fetch();
    }

    /**
     * 修改排序
     * @method changeNavSort
     * @author chengzhigang<1256699215@qq.com>
     */
    public function changeNavSort()
    {
        try {
            $id = input('id');
            $field = input('field');
            $value = input('value');
            $res = $this->AdminNavModel->updateData(array('id' => $id), array($field => $value));
            if ($res) {
                apiReturn('修改成功', 0, array('url' => Cookie('Advert/navlist')));
            } else {
                apiReturn('修改失败');
            }
        } catch (\Exception $e) {
            write_error_log($e); //记录错误日志
            apiReturn(Error_Log);
        }
    }

    /**
     * 新增导航
     * @method addNav
     * @author chengzhigang<1256699215@qq.com>
     */
    public function addNav()
    {
        if (request()->isGet()) {
            $category = $this->AdminNavModel->where('level', 1)->field('id,name')->select();
            $this->assign('category', $category);
            return $this->fetch();
        } else {
            try {
                $data = input('post.');
                if (empty($data['name'])) {
                    apiReturn('导航名称不能为空');
                }
                if (request()->file('image')) {
                    $filearr = upload(request()->file('image'));
                    if ($filearr['status'] == 1) {
                        apiReturn($filearr['msg']);
                    } else {
                        $data['image'] = $filearr['data']['path'];
                    }
                }
                if ($data['pid']) {
                    $data['level'] = 2;
                } else {
                    $data['level'] = 1;
                }
                if ($data['url']) {
                    $data['url'] = perfect_link($data['url']);
                }
                $res = $this->AdminNavModel->createData($data);
                if ($res) {
                    $this->redis->set('HomeNav',null);
                    apiReturn('新增成功', 0, array('url' => Cookie('Advert/navlist')));
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
     * 编辑导航
     * @method editNav
     * @author chengzhigang<1256699215@qq.com>
     */
    public function editNav()
    {
        if (request()->isGet()) {
            $id = input('id');
            $category = $this->AdminNavModel->where('level', 1)->field('id,name')->select();
            $info = $this->AdminNavModel->where('id', $id)->find();
            $this->assign('info', $info);
            $this->assign('category', $category);
            return $this->fetch();
        } else {
            try {
                $data = input('post.');
                if (empty($data['name'])) {
                    apiReturn('导航名称不能为空');
                }
                if (request()->file('image')) {
                    $filearr = upload(request()->file('image'));
                    if ($filearr['status'] == 1) {
                        apiReturn($filearr['msg']);
                    } else {
                        $data['image'] = $filearr['data']['path'];
                    }
                }
                if ($data['url']) {
                    $data['url'] = perfect_link($data['url']);
                }
                if ($data['pid']) {
                    $data['level'] = 2;
                } else {
                    $data['level'] = 1;
                }
                $res = $this->AdminNavModel->updateData(array('id' => $data['id']), $data);
                if ($res) {
                    $this->redis->set('HomeNav',null);
                    apiReturn('编辑成功', 0, array('url' => Cookie('Advert/navlist')));
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
     * 删除导航栏
     * @method delNav
     * @author chengzhigang<1256699215@qq.com>
     */
    public function delNav()
    {
        try {
            $id = input('id');
            $sonCount = $this->AdminNavModel->where('pid', $id)->count();
            if ($sonCount > 0) {
                apiReturn('请先删除下级导航栏');
            }
            $res = $this->AdminNavModel->where('id', $id)->delete();
            if ($res) {
                $this->redis->set('HomeNav',null);
                apiReturn('删除成功', 0, array('url' => Cookie('Advert/navlist')));
            } else {
                apiReturn('删除失败');
            }
        } catch (\Exception $e) {
            write_error_log($e); //记录错误日志
            apiReturn(Error_Log);
        }
    }

    /**
     * 广告列表
     * @method advertlist
     * @author chengzhigang<1256699215@qq.com>
     */
    public function advertlist()
    {
        //列表路径存cookie
        Cookie('Advert/advertlist', request()->url());
        $filter = input('filter/s');
        $where = [];
        if (!empty($filter)) {
            $where[] = array('desc', 'like', '%' . trim($filter) . '%');
        }
        $list = $this->AdminAdvertModel->where($where)->order('create_time desc')->paginate(Page_Num);
        $this->assign('list', $list);
        $this->assign('filter', $filter);
        return $this->fetch();
    }

    /**
     * 新增广告
     * @method addAdvert
     * @author chengzhigang<1256699215@qq.com>
     */
    public function addAdvert()
    {
        if (request()->isGet()) {
            return $this->fetch();
        } else {
            try {
                $data = input('post.');
                if (request()->file('image')) {
                    $filearr = upload(request()->file('image'));
                    if ($filearr['status'] == 1) {
                        apiReturn($filearr['msg']);
                    } else {
                        $data['image'] = $filearr['data']['path'];
                    }
                }
                $res = $this->AdminAdvertModel->createData($data);
                if ($res) {
                    //清空缓存
                    $this->redis->set('AdvertList',null);
                    apiReturn('新增成功', 0, array('url' => Cookie('Advert/advertlist')));
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
     * 编辑广告
     * @method addAdvert
     * @author chengzhigang<1256699215@qq.com>
     */
    public function editAdvert()
    {
        if (request()->isGet()) {
            $id = input('id');
            $info = $this->AdminAdvertModel->where('id', $id)->find();
            $this->assign('info', $info);
            return $this->fetch();
        } else {
            try {
                $data = input('post.');
                if (request()->file('image')) {
                    $filearr = upload(request()->file('image'));
                    if ($filearr['status'] == 1) {
                        apiReturn($filearr['msg']);
                    } else {
                        $data['image'] = $filearr['data']['path'];
                    }
                }
                $res = $this->AdminAdvertModel->updateData(array('id' => $data['id']), $data);
                if ($res) {
                    //清空缓存
                    $this->redis->set('AdvertList',null);
                    apiReturn('编辑成功', 0, array('url' => Cookie('Advert/advertlist')));
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
     * 删除广告
     * @method delAdvert
     * @author chengzhigang<1256699215@qq.com>
     */
    public function delAdvert()
    {
        try {
            $id = input('id');
            $res = $this->AdminAdvertModel->where('id', $id)->delete();
            if ($res) {
                //清空缓存
                $this->redis->set('AdvertList',null);
                apiReturn('删除成功', 0, array('url' => Cookie('Advert/advertlist')));
            } else {
                apiReturn('删除失败');
            }
        } catch (\Exception $e) {
            write_error_log($e); //记录错误日志
            apiReturn(Error_Log);
        }
    }

     /**
     * 友情链接
     * @method linklist
     * @author chengzhigang<1256699215@qq.com>
     */
    public function linklist()
    {
        //列表路径存cookie
        Cookie('Advert/linklist', request()->url());
        $filter = input('filter/s');
        $where = [];
        if (!empty($filter)) {
            $where[] = array('name', 'like', '%' . trim($filter) . '%');
        }
        $list = $this->AdminLinkModel->where($where)->order('create_time desc')->paginate(Page_Num);
        $this->assign('list', $list);
        $this->assign('filter', $filter);
        return $this->fetch();
    }

    /**
     * 新增链接
     * @method addLink
     * @author chengzhigang<1256699215@qq.com>
     */
    public function addLink()
    {
        if (request()->isGet()) {
            return $this->fetch();
        } else {
            try {
                $data = input('post.');
                if (empty($data['name'])) {
                    apiReturn('链接名称不能为空');
                }
                if (empty($data['url'])) {
                    apiReturn('链接地址不能为空');
                }
                $data['url'] = perfect_link($data['url']);
                $res = $this->AdminLinkModel->createData($data);
                if ($res) {
                    $this->redis->set('HomeLink',null);
                    apiReturn('新增成功', 0, array('url' => Cookie('Advert/linklist')));
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
     * 编辑链接
     * @method editLink
     * @author chengzhigang<1256699215@qq.com>
     */
    public function editLink()
    {
        if (request()->isGet()) {
            $id = input('id');
            $info = $this->AdminLinkModel->where('id', $id)->find();
            $this->assign('info', $info);
            return $this->fetch();
        } else {
            try {
                $data = input('post.');
                if (empty($data['name'])) {
                    apiReturn('链接名称不能为空');
                }
                if (empty($data['url'])) {
                    apiReturn('链接地址不能为空');
                }
                $data['url'] = perfect_link($data['url']);
                $res = $this->AdminLinkModel->updateData(array('id' => $data['id']), $data);
                if ($res) {
                    $this->redis->set('HomeLink',null);
                    apiReturn('编辑成功', 0, array('url' => Cookie('Advert/linklist')));
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
     * 删除链接
     * @method delLink
     * @author chengzhigang<1256699215@qq.com>
     * @param id
     */
    public function delLink()
    {
        try {
            $id = input('id');
            $res = $this->AdminLinkModel->where('id', $id)->delete();
            if ($res) {
                $this->redis->set('HomeLink',null);
                apiReturn('删除成功', 0, array('url' => Cookie('Advert/linklist')));
            } else {
                apiReturn('删除失败');
            }
        } catch (\Exception $e) {
            write_error_log($e); //记录错误日志
            apiReturn(Error_Log);
        }
    }

    /**
     * 修改排序
     * @method changeLinkSort
     * @author chengzhigang<1256699215@qq.com>
     */
    public function changeLinkSort()
    {
        try {
            $id = input('id');
            $field = input('field');
            $value = input('value');
            $res = $this->AdminLinkModel->updateData(array('id' => $id), array($field => $value));
            if ($res) {
                $this->redis->set('HomeLink',null);
                apiReturn('修改成功', 0, array('url' => Cookie('Advert/linklist')));
            } else {
                apiReturn('修改失败');
            }
        } catch (\Exception $e) {
            write_error_log($e); //记录错误日志
            apiReturn(Error_Log);
        }
    }

}
