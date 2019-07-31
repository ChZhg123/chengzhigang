<?php
namespace app\index\controller;
use think\facade\Log;
class Index extends Base
{

    /**
     * 首页页面输出
     * @method index
     * @author chengzhigang<1256699215@qq.com>
     */
    public function index()
    {
        Cookie('ReturnUrl', request()->url());//记录当前路径
        //获取轮播图
        if(!$this->redis->get('Homebanner')){
            $where = [];
            $perpage = 5;//默认展示5条
            $where[] = ['type','eq',1];
            $banner = $this->AdminBannerModel->where($where)->field('name,image,url')->page(1,$perpage)->order('sort')->select()->toArray();
            if(count($banner)){
                $this->redis->setex('Homebanner',3600,json_encode($banner));
            }
        }else{
            $banner = json_decode($this->redis->get('Homebanner'),true);
        }
        //获取置顶文章
        if(!$this->redis->get('Homestick')){
            $perpage = 2;//默认展示2条
            $condition = [];
            $condition[] = ['show','eq',2];
            $condition[] = ['stick','eq',2];
            $condition[] = ['image','neq','null'];
            $article = $this->ArticleInfoModel->where($condition)->field('id,title,image')->page(1,$perpage)->order('sort desc')->select()->toArray();
            if(count($article)){
                $this->redis->setex('Homestick',3600,json_encode($article));
            }
        }else{
            $article = json_decode($this->redis->get('Homestick'),true);
        }
        //获取文章分类
        if(!$this->redis->get('Homecate')){
            $articlelist = [];
            $cateWhere = [];
            $cateWhere['is_show'] = 1;
            $cateWhere['level'] = 1;
            $articlelist['article'] = [];
            $articleCate = $this->ArticleCateModel->where($cateWhere)->field('id,name')->page(1,5)->order('sort desc')->select()->toArray();
            foreach($articleCate as $val){
                $list = $this->ArticleCateRelateModel->alias('a')->join('article_info b','a.article_id=b.id')->where('a.cate_id',$val['id'])->where('b.show',2)->field('b.*')->group('a.article_id')->page(1,7)->order('b.sort desc')->select()->toArray();
                foreach($list as &$v){
                    $v['image'] = (is_https()?'https://':'http://').Img_Url.'/'.$v['image'];
                }
                $articlelist['article'][] = $list;
            }
            $articlelist['cate'] = $articleCate;
            if(count($articlelist['cate'])){
                $this->redis->setex('Homecate',3600,json_encode($articlelist));
            }
        }else{
            $articlelist = json_decode($this->redis->get('Homecate'),true);
        }
        //特别推荐
        if(!$this->redis->get('HomeRecomd')){
            $where = [];
            $where['show'] = 2;
            $where['recomd'] = 2;
            $perpage = 6;//默认展示6条
            $recomdlist = $this->ArticleInfoModel->where($where)->field('id,title,image,author,excerpt,DATE(create_time) as time')->page(1,$perpage)->order('sort desc')->select()->toArray();
            if(count($recomdlist)){
                $this->redis->setex('HomeRecomd',3600,json_encode($recomdlist));
            }
        }else{
            $recomdlist = json_decode($this->redis->get('HomeRecomd'),true);
        }
        //文章列表总数
        $where = [];
        $where[] = ['show','eq',2];
        $count = $this->ArticleInfoModel->cache(true, 300)->where($where)->count();
        $this->assign('banner',$banner);
        $this->assign('article',$article);
        $this->assign('articlelist',$articlelist);
        $this->assign('recomdlist',$recomdlist);
        $this->assign('count',$count);
        return $this->fetch();
    }

    /**
     * 用户登录
     * @method login
     * @author chengzhigang<1256699215@qq.com>
     * @param email password captcha remember
     */
    public function login(){
        if(request()->isGet()){
            return $this->fetch();
        }else{
            try{
                $data = input('post.');
                $email = $data['email'];
                if(!check_email($email)){
                    apiReturn('邮箱格式不正确');
                }
                //验证邮箱是否已注册
                $userinfo = $this->UserInfoModel->where('email',$email)->find();
                if(empty($userinfo)){
                    apiReturn('该邮箱还未注册');
                }
                $password = $data['password'];
                if(empty($password)){
                    apiReturn('密码不能为空');
                }
                if(encryptPwd($password)!=$userinfo['password']){
                    apiReturn('密码不正确');
                }else{
                    session('user_id', $userinfo['id']);
                    session('nickname',$email);
                    session('user_info',$userinfo);
                    //记住密码
                    if($data['remember']==1){
                        cookie('cookie_id',encryptPwd($userinfo['id']),3600*24*30);
                    }
                    apiReturn('登录成功',0,array('url'=>empty(Cookie('ReturnUrl'))?'index':Cookie('ReturnUrl')));
                }
            }catch(\Exception $e){
                write_error_log($e); //记录错误日志
                apiReturn(Error_Log);
            }
        }
    }

    /**
     * 用户邮箱注册
     * @method regist
     * @author chengzhigang<1256699215@qq.com>
     * @param email password captcha remember
     */
    public function regist(){
        if(request()->isGet()){
            return $this->fetch();
        }else{
            try{
                $data = input('post.');
                $email = $data['email'];
                if(!check_email($email)){
                    apiReturn('邮箱格式不正确');
                }
                //验证邮箱是否已注册
                $userinfo = $this->UserInfoModel->where('email',$email)->find();
                if(!empty($userinfo)){
                    apiReturn('该邮箱已注册');
                }
                if(empty($data['captcha'])){
                    apiReturn('邮箱验证码不能为空');
                }
                $code = $this->redis->get($email);
                if($code!=$data['captcha']){
                    apiReturn('邮箱验证码不正确');
                }else{
                    $this->redis->del($email);
                }
                $password = $data['password'];
                if(empty($password)){
                    apiReturn('密码不能为空');
                }
                if(!check_password($password)){
                    apiReturn('密码格式不正确');
                }
                $password = encryptPwd($password);
                //注册用户
                $userData = [];
                $userData['email'] = $email;
                $userData['nickname'] = $email;
                $userData['password'] = $password;
                $res = $this->UserInfoModel->createData($userData);
                if($res){
                    //注册成功
                    session('user_id', $this->UserInfoModel->id);
                    session('nickname',$email);
                    session('user_info',$this->UserInfoModel->get($this->UserInfoModel->id));
                    $this->UserInfoModel->updateData(['id'=>$this->UserInfoModel->id],['encrypt_pwd'=>encryptPwd($this->UserInfoModel->id)]);
                    //记住密码
                    if($data['remember']==1){
                        cookie('cookie_id',encryptPwd($this->UserInfoModel->id),3600*24*30);
                    }
                    apiReturn('注册成功',0,array('url'=>empty(Cookie('ReturnUrl'))?'index':Cookie('ReturnUrl')));
                }else{
                    apiReturn('注册失败');
                }
            }catch(\Exception $e){
                write_error_log($e); //记录错误日志
                apiReturn(Error_Log);
            }
        }
    }

    /**
     * 退出登录
     * @method loginout
     * @author chengzhigang
     */
    public function loginout()
    {
        try {
            session('user_id',null);
          	session('nickname',null);
            cookie('cookie_id',null);
            apiReturn('退出成功',0,array('url'=>empty(Cookie('ReturnUrl'))?'index':Cookie('ReturnUrl')));
        } catch (\Exception $e) {
            write_error_log($e); //记录错误日志
        }
    }

    /**
     * 邮箱发送验证码
     * @method send_code
     * @author chengzhigang<1256699215@qq.com>
     * @param email
     */
    public function send_code(){
        try{
            $email = input('email');
            //邮箱验证
            if(!check_email($email)){
                apiReturn('邮箱格式不正确');
            }
            $code = rand(100000,999999);
            $this->redis->setex($email,300,$code);
            $title = "QQ邮箱验证";
            $content = "您好！您的邮箱验证码是：" . $code . "。如非本人操作，请忽略本消息" ;
            $res = send_email($email,$title,$content);
            if($res['status']==0){
                apiReturn('验证码已经发送，请注意查收',0);
            }else{
                apiReturn($res['msg']);
            }
        }catch(\Exception $e){
            write_error_log($e); //记录错误日志
            apiReturn(Error_Log);
        }
    }

    /**
     * gitHub登录回调
     * @method gitHubNotify
     * @author chengzhigang<1256699215@qq.com>
     * @param 
     * @return 
     */
    public function gitHubNotify(){
        try{
            if (isset($_GET['code'])) {
                $access_token_url = 'https://github.com/login/oauth/access_token';
                $params = array(
                    'client_id'     => '',
                    'client_secret' => '',
                    'code'          => $_GET['code'],
                    'state'         => $_GET['state'],
                );
                $result = httpsRequest($access_token_url, $params);
                if ($result&&isset($result['access_token'])) {
                    $access_token = $result['access_token'];
                    $url = 'https://api.github.com/user?'.$access_token;
                    $headers[] = 'Authorization: token '.$access_token;
                    $headers[] = "User-Agent: 观海听潮";
                    $resultData = httpsRequest($url,[],$headers);
                    if ($resultData['id']) {
                        $this -> three_login($resultData,3);
                    }
                }
                return redirect(empty(Cookie('ReturnUrl'))?'index':Cookie('ReturnUrl'));
            }
        }catch(\Exception $e){
            write_error_log($e); //记录错误日志
        }
    }

    /**
     * 微博登录回调
     * @method weiboNotify
     * @author chengzhigang<1256699215@qq.com>
     * @param 
     * @return 
     */
    public function weiboNotify(){
        try{
            if (isset($_GET['code'])) {
                //微博恶心之处post提交参数写链接里面，所以没有用公共方法请求
                $access_token_url = "https://api.weibo.com/oauth2/access_token?client_id=&client_secret=&grant_type=authorization_code&code=".$_GET['code']."&redirect_uri=http://chengzhigang.cn/index/index/weiboNotify";
                $curl = curl_init();
                curl_setopt($curl, CURLOPT_URL, $access_token_url);
                curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
                curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);
                curl_setopt($curl, CURLOPT_POST, 1);
                curl_setopt($curl, CURLOPT_POSTFIELDS, []);
                curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($curl, CURLOPT_HTTPHEADER,["Accept: application/json"]);
                $output = curl_exec($curl);
                curl_close($curl);
                $result = json_decode($output,true);
                if($result&&isset($result['access_token'])){
                    $access_token = $result['access_token'];
                    $uid = $result['uid'];
                    $url = "https://api.weibo.com/2/users/show.json?access_token=".$access_token."&uid=".$uid;
                    $resultData = httpsRequest($url);
                    if($resultData){
                        $this -> three_login($resultData,5);
                    }
                }
                return redirect(empty(Cookie('ReturnUrl'))?'index':Cookie('ReturnUrl'));
            }
        }catch(\Exception $e){
            write_error_log($e); //记录错误日志
        }
    }

    /**
     * 百度登录回调
     * @method baiduNotify
     * @author chengzhigang<1256699215@qq.com>
     * @param 
     * @return 
     */
    public function baiduNotify(){
        try{
            if (isset($_GET['code'])) {
                $access_token_url = 'https://openapi.baidu.com/oauth/2.0/token';
                $params = array(
                    'client_id'     => '',
                    'client_secret' => '',
                    'redirect_uri'  => 'http://chengzhigang.cn/index/index/baiduNotify',
                    'grant_type'    => 'authorization_code',
                    'code'          => $_GET['code'],
                    'state'         => $_GET['state'],
                );
                $result = httpsRequest($access_token_url, $params);
                if ($result&&isset($result['access_token'])) {
                    $access_token = $result['access_token'];
                    $url = 'https://openapi.baidu.com/rest/2.0/passport/users/getInfo?access_token='.$access_token;
                    $resultData = httpsRequest($url);
                    if ($resultData['userid']) {
                        $this -> three_login($resultData,6);
                    }
                }
                return redirect(empty(Cookie('ReturnUrl'))?'index':Cookie('ReturnUrl'));
            }
        }catch(\Exception $e){
            write_error_log($e); //记录错误日志
        }
    }   

    public function qqNotify(){
        try{
            if (isset($_GET['code'])) {
                $access_token_url = 'https://graph.qq.com/oauth2.0/token';
                $params = array(
                    'client_id'     => '',
                    'client_secret' => '',
                    'redirect_uri'  => 'http://chengzhigang.cn/index/index/qqNotify',
                    'grant_type'    => 'authorization_code',
                    'code'          => $_GET['code'],
                    'state'         => $_GET['state'],
                );
                $result = httpsRequest($access_token_url, $params);
                parse_str($result,$result);
                if ($result&&isset($result['access_token'])) {
                    $access_token = $result['access_token'];
                    $url = 'https://graph.qq.com/oauth2.0/me?access_token='.$access_token;
                    $resultData = httpsRequest($url);
                    $lpos = strpos($resultData, "(");
                    $rpos = strrpos($resultData, ")");
                    $resultData = substr($resultData, $lpos+1, $rpos-$lpos-1);
                    $resultData = json_decode($resultData,true);
                    if($resultData['openid']){
                        $url = 'https://graph.qq.com/user/get_user_info?access_token='.$access_token.'&oauth_consumer_key=101649470&openid='.$resultData['openid'];
                        $userData = httpsRequest($url);
                        $userData['openid'] = $resultData['openid'];
                        $this -> three_login($userData,2);
                    }
                }
                Log::info(Cookie('ReturnUrl'));
                return redirect(empty(Cookie('ReturnUrl'))?'index':Cookie('ReturnUrl'));
            }
        }catch(\Exception $e){
            write_error_log($e); //记录错误日志
        }
    }

    public function three_login($data,$type){
        $userData = [];
        $where = [];
        $where['login_type'] = $type;
        $userData['login_type'] = $type;
        switch($type){
            case 2:
                $userData['nickname'] = $data['nickname'];
                $userData['open_id'] = $data['openid'];
                $userData['head_pic'] = $data['figureurl'];
                $where['open_id'] = $data['openid'];
                $userData['gender'] = $data['gender']=='男'?1:2;
                //QQ登录
                break; 
            case 3:
                $userData['nickname'] = $data['login'];
                $userData['github_id'] = $data['id'];
                $userData['head_pic'] = $data['avatar_url'];
                $userData['email'] = $data['email'];
                $where['github_id'] = $data['id'];
                break; 
                //gitHub登录
            case 5:
                $userData['nickname'] = $data['screen_name'];
                $userData['weibo_id'] = $data['id'];
                $userData['head_pic'] = $data['profile_image_url'];
                $userData['gender'] = $data['gender']=='m'?1:($data['gender']=='f'?2:3);
                $where['weibo_id'] = $data['id'];
                break; 
                //微博登录
            case 6:
                $userData['nickname'] = $data['username'];
                $userData['baidu_id'] = $data['userid'];
                $userData['head_pic'] = 'http://tb.himg.baidu.com/sys/portraitn/item/'.$data['portrait'];
                $userData['gender'] = $data['sex']==0?2:1;
                $where['baidu_id'] = $data['userid'];
                break; 
                //百度登录
        }
        // 处理获取到的数据
        $userInfo = $this->UserInfoModel->where($where)->find();
        if($userInfo){
            session('user_id', $userInfo['id']);
            session('nickname',$userInfo['nickname']);
            session('user_info',$userInfo);
            cookie('cookie_id',encryptPwd($userInfo['id']),3600*24*30);
        }else{
            $res = $this->UserInfoModel->createData($userData);
            if($res){
                //注册成功
                $this->UserInfoModel->updateData(['id'=>$this->UserInfoModel->id],['encrypt_pwd'=>encryptPwd($this->UserInfoModel->id)]);
                session('user_id', $this->UserInfoModel->id);
                session('nickname',$userData['nickname']);
                session('user_info',$this->UserInfoModel->get($this->UserInfoModel->id));
                cookie('cookie_id',encryptPwd($this->UserInfoModel->id),3600*24*30);
            }
        }
    }
}
