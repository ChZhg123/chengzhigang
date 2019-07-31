<?php
use think\Db;
use think\facade\Env;
use phpMailer\PHPMailer;
use Qiniu\Auth;
use Qiniu\Storage\UploadManager;
use Sphinx\SphinxClient;
use Qiniu\Zone;
use Qiniu\Config;
use think\facade\Log;
use think\facade\Config as Rconfig;
/**
 * 接口返回json
 * @method apiReturn
 * @author chengzhigang
 * @return msg 提示信息 code(0成功 1失败 2重新登录) data 返回数据 time 请求时间
 */
function apiReturn($msg = "", $code = 1, $data = "")
{
    $result = array(
        'code' => $code,
        'msg' => $msg,
        'data' => $data,
        'time' => date('Y-m-d H:i:s', $_SERVER['REQUEST_TIME']),
    );
    exit(json_encode($result));
}
/**
 * http请求post
 */
function httpsRequest($url, $data = null,$headers=[]) {
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);
    if (!empty($data)) {
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
    }
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($curl, CURLOPT_HTTPHEADER,["Accept: application/json"]);
    if(!empty($headers)){
        $headers[] = "Accept: application/json";
        curl_setopt($curl, CURLOPT_HTTPHEADER,$headers);
    }else{
        curl_setopt($curl, CURLOPT_HTTPHEADER,["Accept: application/json"]);
    }
    $output = curl_exec($curl);
    curl_close($curl);
    if(is_null(json_decode($output))){
        return $output;
    }else{
        return json_decode($output,true);
    }
}
/**
 * 创建生成token
 * @method buildRandStr
 * @author chengzhigang
 * @param  $n 数字 默认4
 * @return token
 */
function buildRandStr($n = 4)
{
    $chars = array(
        "A", "B", "C", "D", "E", "F", "G",
        "H", "I", "J", "K", "L", "M", "N",
        "O", "P", "Q", "R", "S", "T",
        "U", "V", "W", "X", "Y", "Z",
    );
    $charsLen = count($chars) - 1;
    shuffle($chars);
    $output = "";
    for ($i = 0; $i < $n; $i++) {
        $output .= $chars[mt_rand(0, $charsLen)];
    }
    return strtoupper(md5($output));
}

/**
 * 密码加密方法
 * @method encryptPwd
 * @author chengzhigang
 * @param $password
 * @return
 */
function encryptPwd($password)
{
    return md5(sha1(trim($password)));
}
/**
 * 获取用户性别
 * @method getGender
 * @author chengzhigang<1256699215@qq.com>
 */
function getGender($gender){
    switch($gender){
        case 1:
            return '男';
            break;
        case 2:
            return '女';
            break;
        default:
            return '未知';
            break;
    }
}
/**
 * 获取登录来源
 * @method getLoginType
 * @author chengzhigang<1256699215@qq.com>
 */
function getLoginType($type){
    switch($type){
        case 1:
            return '邮箱注册';
            break;
        case 2:
            return 'QQ注册';
            break;
        case 3:
            return 'gitHub注册';
            break;
        case 4:
            return '手机注册';
            break;
        case 5:
            return '微博注册';
            break;
        default:
            return '百度注册';
            break;
    }
}
/**
 * 签名算法
 * @method makeSign
 * @author chengzhigang
 * @param $param请求参数 $sign_key签名key
 * @return
 */
function makeSign($param, $sign_key)
{
    ksort($param); //按字典序排序参数
    $string = "";
    foreach ($param as $k => $v) {
        if ($k != "sign" && $v != "" && !is_array($v)) {
            $string .= $k . "=" . $v . "&";
        }
    }
    $string = trim($string, "&");
    //签名步骤二：在string后加入KEY
    $string = $string . "&key=" . $sign_key;
    //签名步骤三：MD5加密
    $string = md5(sha1($string));
    //签名步骤四：所有字符转为大写
    $result = strtoupper($string);
    return $result;
}

/**
 * 验证签名
 * @method checkSign
 * @author chengzhigang
 * @param $param请求参数
 * @return
 */
function checkSign($param)
{
    $sign = $param['sign'];
    unset($param['sign']);
    $result = makeSign($param, Sign_Key);
    if ($sign == $result) {
        return true;
    } else {
        return false;
    }
}

/**
 * 生成错误记录
 * @method write_error_log
 * @author chengzhigang
 * @param $e
 * @return
 */
function write_error_log($e)
{
    if (!empty($e)) {
        $error = array(
            'code' => $e->getCode(),
            'msg' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'create_time' => date('Y-m-d H:i:s'),
        );
        Db::name('error_log')->insert($error);
    }
}

/**
 * 记录管理员登录日志
 * @method write_login_log
 * @author chengzhigang
 * @param id username
 * @return
 */
function write_login_log($id, $username)
{
    $login = array(
        'admin_id' => $id,
        'username' => $username,
        'login_ip' => request()->ip(),
        'create_time' => date('Y-m-d H:i:s'),
    );
    Db::name('login_log')->insert($login);
}
/**
 * 记录用户浏览文章
 * @method write_article_hit_log
 * @author chengzhigang
 * @param $article_id $user_id
 * @return
 */
function write_article_hit_log($article_id,$user_id,$time){
    $log = array(
        'article_id'=>$article_id,
        'user_id'=>$user_id,
        'create_time'=>$time
    );
    model('article_history')->createData($log);
}
/**
 * 记录用户点赞文章
 * @method write_article_like_log
 * @author chengzhigang<1256699215@qq.com>
 * @param $data
 */
function write_article_like_log($data){
    model('article_like')->createData($data);
}
/**
 * 阿里云发送短信
 * @method send_sms
 * @author chengzhigang
 * @param $phone $code
 * @return
 */
function send_sms($phone, $code)
{
    require_once Env::get('extend_path') . '/aliyunSms/aliyun-php-sdk-core/Config.php';
    require_once Env::get('extend_path') . '/aliyunSms/Dysmsapi/Request/V20170525/SendSmsRequest.php';
    //此处需要替换成自己的AK信息
    $accessKeyId = "";
    $accessKeySecret = "";
    //短信API产品名
    $product = "Dysmsapi";
    //短信API产品域名
    $domain = "dysmsapi.aliyuncs.com";
    //暂时不支持多Region
    $region = "cn-hangzhou";

    //初始化访问的acsCleint
    $profile = \DefaultProfile::getProfile($region, $accessKeyId, $accessKeySecret);
    \DefaultProfile::addEndpoint("cn-hangzhou", "cn-hangzhou", $product, $domain);
    $acsClient = new \DefaultAcsClient($profile);

    $request = new \Dysmsapi\Request\V20170525\SendSmsRequest;
    //必填-短信接收号码
    $request->setPhoneNumbers($phone);
    //必填-短信签名
    $request->setSignName("");
    //必填-短信模板Code
    $request->setTemplateCode("");
    //选填-假如模板中存在变量需要替换则为必填(JSON格式)
    $request->setTemplateParam("{\"code\":\"$code\"}");

    //发起访问请求
    $acsResponse = $acsClient->getAcsResponse($request);
    return $acsResponse;
}

/**
 * QQ邮箱发送
 * @method send_email
 * @author chengzhigang<1256699215@qq.com>
 * @param $email $title $content
 * @return status 0成功 1失败
 */
function send_email($email,$title,$content)
{
    $toemail = $email; //定义收件人的邮箱
    $sendmail = ''; //发件人邮箱
    $sendmailpswd = ""; //客户端授权密码,而不是邮箱的登录密码，就是手机发送短信之后弹出来的一长串的密码
    $send_name = '观海听潮'; // 设置发件人信息，如邮件格式说明中的发件人，
    $to_name = '在线用户'; //设置收件人信息，如邮件格式说明中的收件人
    $mail = new phpMailer();
    $mail->isSMTP(); // 使用SMTP服务
    $mail->CharSet = "utf8"; // 编码格式为utf8，不设置编码的话，中文会出现乱码
    $mail->Host = "SMTP.qq.com"; // 发送方的SMTP服务器地址
    $mail->SMTPAuth = true; // 是否使用身份验证
    $mail->Username = $sendmail; //// 发送方的
    $mail->Password = $sendmailpswd; //客户端授权密码,而不是邮箱的登录密码！
    $mail->SMTPSecure = "ssl"; // 使用ssl协议方式
    $mail->Port = 465; //  sina端口110或25） //qq  465 587
    $mail->setFrom($sendmail, $send_name); // 设置发件人信息，如邮件格式说明中的发件人，
    $mail->addAddress($toemail, $to_name); // 设置收件人信息，如邮件格式说明中的收件人，
    $mail->addReplyTo($sendmail, $send_name); // 设置回复人信息，指的是收件人收到邮件后，如果要回复，回复邮件将发送到的邮箱地址
    $mail->Subject = $title; // 邮件标题
    $mail->Body = $content;//"您好！您的邮箱验证码是：" . $code . "。如非本人操作，请忽略本消息" ;
    if (!$mail->send()) {
		return array('status'=>1,'msg'=>$mail->ErrorInfo);
    } else {
		return array('status'=>0,'msg'=>"发送成功");
    }
}

/**
 * 单文件上传
 * @method upload
 * @author chengzhigang<1256699215@qq.com>
 * @param $file文件 $type文件类型（image file） $config文件配置
 */
function upload($file,$type="image",$config=[])
{
    $default = config('upload.type');
    //判断文件类型
    if(!isset($default[$type])){
        return array('status' => 1, 'msg' => '文件类型错误', 'data' => []);
    }else{
        if($file->isValid()){
            $info = $file->getInfo();
            $fileName = $info['name'];
            $realPath = $info['tmp_name'];
            $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            $size = $info['size'];
            $md5 = $file -> hash('md5');
            $fileType = $info['type'];
            $newName = $ext . '/' . md5(microtime(true)) . '.' . $ext;
            $defaultConfig = $default[$type];
            $fileExt = isset($defaultConfig['ext'])?$defaultConfig['ext']:$config['ext'];
            $fileSize = isset($defaultConfig['size'])?$defaultConfig['size']:$config['size'];
            if(strpos($fileExt,$ext) !== false){
                //文件大小验证
                if($size>$fileSize){
                    return ['status'=>1,'msg'=>'请上传'.floatval($fileSize)/(1024*1024).'M以内的文件','data'=>''];
                }
                if($type=="image"){
                    $uploadData = model('file_upload')->where('md5',$md5)->find();
                    if($uploadData){
                        return array('status' => 0, 'msg' => '上传成功', 'data' => $uploadData);
                    }
                    $result = checkSensitive($realPath,1);
                    if($result['status']!=1){
                        return array('status' => 1, 'msg' => '图片不合规', 'data' => []);
                    }
                }
                $image = \think\Image::open($realPath);
                //添加水印
                if($type=='image'&&(isset($config['water'])&&isset($config['water']['status'])&&$config['water']['status'])){
                    //水印类型
                    $waterType = isset($config['water']['type'])?$config['water']['type']:$defaultConfig['water']['type'];
                    if($waterType=='image'){
                        //图片水印
                        $waterImage = isset($config['water']['image'])?$config['water']['image']:$defaultConfig['water']['image'];
                        $waterFile = isset($waterImage['file'])?$waterImage['file']:$defaultConfig['water']['image']['file'];
                        $waterPosition = isset($waterImage['position'])?$waterImage['position']:$defaultConfig['water']['image']['position'];
                        $waterOpacity = isset($waterImage['opacity'])?$waterImage['opacity']:$defaultConfig['water']['image']['opacity'];
                        $image->water($waterFile,$waterPosition,$waterOpacity);
                    }else{
                        //文字水印
                        $waterFont = isset($config['water']['font'])?$config['water']['font']:$defaultConfig['water']['font'];
                        $waterFile = isset($waterFont['file'])?$waterFont['file']:$defaultConfig['water']['font']['file'];
                        $waterText = isset($waterFont['text'])?$waterFont['text']:$defaultConfig['water']['font']['text'];
                        $waterSize = isset($waterFont['size'])?$waterFont['size']:$defaultConfig['water']['font']['size'];
                        $waterColor = isset($waterFont['color'])?$waterFont['color']:$defaultConfig['water']['font']['color'];
                        $image->text($waterText,Env::get('root_path').$waterFile,$waterSize,$waterColor,9,-15)->save($realPath);
                    }
                }
                //生成缩略图
                if($type=='image'&&(isset($config['thumb'])&&isset($config['thumb']['status'])&&$config['thumb']['status'])){
                    $thumbWidth = isset($config['thumb']['width'])?$config['thumb']['width']:$defaultConfig['thumb']['width'];
                    $thumbHeight = isset($config['thumb']['height'])?$config['thumb']['height']:$defaultConfig['thumb']['height'];
                    $thumbType = isset($config['thumb']['type'])?$config['thumb']['type']:$defaultConfig['thumb']['type'];
                    $image->thumb($thumbWidth,$thumbHeight,$thumbType)->save($realPath);
                }
                //上传七牛云
                $qiniu = config('upload.qiniu');
                require_once '../vendor/qiniu/php-sdk/autoload.php';
                $auth = new Auth($qiniu['access_key'],$qiniu['secret_key']);
                // 生成上传Token
                $token = $auth->uploadToken($qiniu['bucket']);
                $zone = new Zone(array('upload-z1.qiniup.com'));//默认域名是华北地区
                $cfg = new Config($zone);
                $uploadMgr = new UploadManager($cfg); 
                list($ret, $err) = $uploadMgr->putFile($token, $newName, $realPath); 
                if ($err !== null) { 
                    return array('status' => 1, 'msg' => $err->getError(), 'data' => []);
                } else { 
                    $pathUrl = (is_https()?'https://':'http://').$qiniu['domain_name']. '/'. $ret['key'];
                    //记录上传日志
                    $data = array(
                        'name' => $fileName,
                        'path' => $ret['key'],
                        'pathurl' => $pathUrl,
                        'size' => $size,
                        'ext' => $ext,
                        'type'=>$fileType,
                        'md5' => $md5,
                        'create_time'=>date('Y-m-d H:i:s')
                    );
                    model('file_upload')->insert($data);
                    return array('status' => 0, 'msg' => '上传成功', 'data' => $data);
                } 
            }else{
                return array('status' => 1, 'msg' => '文件类型不支持', 'data' => []);
            }
        }else{
            return array('status' => 1, 'msg' => '文件不存在', 'data' => []);
        }
    }
}
/**
 * 多文件上传
 * @method upload
 * @author chengzhigang<1256699215@qq.com>
 * @param $files文件
 */
function uploads($files,$type="image",$config=[])
{
    if(is_array($files)){
        $results = [];
        foreach($files as $file){
            $result = upload($file,$type,$config);
            if($result['status']==0){
                $results[] = $result['data'];
            }else{
                return $result;
            }
        }
        return ['status'=>0,'msg'=>'上传成功','data'=>$results];
    }
}
/**
 * 百度检测图片内容是否敏感
 * @param content 图片路径或者文本内容 
 * @param type 1图片 2文本
 */
function checkSensitive($content,$type){
    $app_id = '';
    $api_key = '';
    $secret_key = '';
    require_once Env::get('extend_path')."BaiduSensitive/AipImageCensor.php";
    $client = new \AipImageCensor($app_id, $api_key, $secret_key);
    if($type==1){
        //图片审核
        $result = $client->imageCensorUserDefined(file_get_contents($content));
        if($result&&$result['conclusionType']==1){
            return ['status'=>1,'msg'=>'合规','data'=>''];
        }else{
            return ['status'=>2,'msg'=>'不合规','data'=>$result['data']];
        }
    }else{
        //文本审核
        $result = $client->textCensorUserDefined($content);
        if($result&&$result['conclusionType']==1){
            return ['status'=>1,'msg'=>'合规','data'=>''];
        }else{
            return ['status'=>2,'msg'=>'不合规','data'=>$result['data']];
        }
    }
}
/**
 * 获取管理员权限
 * @method get_side_navs
 * @author chengzhigang
 * @param
 * @return
 */
function get_side_navs($admin_id)
{
    $data = []; //管理员权限
    $menulist = []; //管理员菜单权限
    $navs_arr = [];
    $admin_navs = Db::name('admin_role a')->join('admin_user b', 'a.id=b.role_id')->where('b.id', $admin_id)->value('a.navs');
    $navs = Db::name('side_nav')->where('level', 'between', '1,3')->field('id,name,icon,href,pid,level')->order('sort')->select();
    foreach ($navs as $k => $nav) {
        $navs_arr[$nav['id']] = $nav;
    }
    if (empty($admin_navs)) {
        apiReturn('您没有权限登录平台');
    } else {
        $admin_arr = explode(',', $admin_navs);
        foreach ($admin_arr as $val) {
            if (isset($navs_arr[$val])) {
                $navs_arr[$val]['url'] = $navs_arr[$val]['href'];
                if ($navs_arr[$val]['level'] == 1) {
                    if (!isset($data[$val])) {
                        $data[$val] = $navs_arr[$val];
                        $data[$val]['child'] = [];
                    } else {
                        $data[$val]['id'] = $navs_arr[$val]['id'];
                        $data[$val]['href'] = $navs_arr[$val]['href'];
                        $data[$val]['name'] = $navs_arr[$val]['name'];
                        $data[$val]['level'] = $navs_arr[$val]['level'];
                        $data[$val]['icon'] = $navs_arr[$val]['icon'];
                        $data[$val]['pid'] = $navs_arr[$val]['pid'];
                    }
                } elseif ($navs_arr[$val]['level'] == 2) {
                    $navs_arr[$val]['href'] = !empty($navs_arr[$val]['href']) ? url($navs_arr[$val]['href']) : '';
                    if (!isset($data[$navs_arr[$val]['pid']]['child'])) {
                        $data[$navs_arr[$val]['pid']]['child'] = [];
                    }
                    $data[$navs_arr[$val]['pid']]['child'][$val] = $navs_arr[$val];
                } else {
                    $navs_arr[$val]['href'] = !empty($navs_arr[$val]['href']) ? url($navs_arr[$val]['href']) : '';
                    $pid = Db::name('side_nav')->where('id', $navs_arr[$val]['pid'])->value('pid');
                    if (!isset($data[$pid])) {
                        $data[$pid] = [];
                    }
                    if (!isset($data[$navs_arr[$pid]['id']]['child'])) {
                        $data[$navs_arr[$pid]['id']]['child'] = [];
                    }
                    if (!isset($data[$navs_arr[$pid]['id']]['child'][$navs_arr[$val]['pid']]['child'])) {
                        $data[$navs_arr[$pid]['id']]['child'][$navs_arr[$val]['pid']]['child'] = [];
                    }
                    $data[$navs_arr[$pid]['id']]['child'][$navs_arr[$val]['pid']]['child'][$val] = $navs_arr[$val];
                    //单独的菜单权限（用于页面展示）
                    $menulist[] = $navs_arr[$val];
                }
            }
        }
    }
    foreach ($data as &$val) {
        if (isset($val['child'])) {
            $val['child'] = array_values($val['child']);
            foreach ($val['child'] as &$vv) {
                if (isset($vv['child'])) {
                    $vv['child'] = array_values($vv['child']);
                }
            }
        }
    }
    session('menulist', $menulist);
    return array_values($data);
}

/**
 * 获取权限树
 * @method getTreeList
 * @author chengzhigang<1256699215@qq.com>
 * @param $role_id
 * @return
 */
function getTreeList($role_id = 0)
{
    $treelist = model('side_nav')->where('level', 1)->field('id,name,level,pid,icon')->order('sort')->select();
    $rolenavs = model('admin_role')->where('id', $role_id)->value('navs');
    $rolearr = explode(',', $rolenavs);
    foreach ($treelist as &$val) {
        //一级
        $val['select'] = 0;
        if (in_array($val['id'], $rolearr)) {
            $val['select'] = 1;
        }
        $val['child'] = model('side_nav')->where('pid', $val['id'])->field('id,name,level,pid,icon')->order('sort')->select();
        foreach ($val['child'] as &$vv) {
            //二级
            $vv['select'] = 0;
            if (in_array($vv['id'], $rolearr)) {
                $vv['select'] = 1;
            }
            $vv['child'] = model('side_nav')->where('pid', $vv['id'])->field('id,name,level,pid,icon')->order('sort')->select();
            foreach ($vv['child'] as &$vvv) {
                //三级
                $vvv['select'] = 0;
                if (in_array($vvv['id'], $rolearr)) {
                    $vvv['select'] = 1;
                }
            }
        }
    }
    return $treelist;
}

/**
 * 获取文章类型
 * @method getCategory
 * @author chengzhigang<1256699215@qq.com>
 * @param $category数组
 */
function getArticleCate($article_id)
{
    $cates = model('article_cate_relate')->alias('a')->join('article_cate b','a.cate_id=b.id','left')->where('a.article_id',$article_id)->column('b.name');
    return implode(',',$cates);
}
/**
 * 获取商品分类
 * @method goods_id
 * @author chengzhigang<1256699215@qq.com>
 * @param 
 * @return 
 */
function getGoodsCate($goods_id){
    $cates = model('goods_cate_relate')->alias('a')->join('goods_cate b','a.cate_id=b.id','left')->where('a.goods_id',$goods_id)->column('b.name');
    return implode(',',$cates);
}

/**
 * 获取分类名称
 * @method getCateName
 * @author chengzhigang<1256699215@qq.com>
 * @param $cate_id
 */
function getCateName($cate_id){
    return model('goods_cate')->where('id',$cate_id)->value('name');
}

/**
 * 获取一级的分类id
 * @method getParentCate
 * @author chengzhigang<1256699215@qq.com>
 * @param $catearr
 */
function getParentCate($catearr){
    $newCate = [];
    foreach($catearr as $v){
        $pid = model('goods_cate')->where('id',$v)->value('pid');
        if($pid){
            $ppid = model('goods_cate')->where('id',$pid)->value('pid');
            if($ppid){
                $newCate[] = $ppid;
            }else{
                $newCate[] = $pid;
            }
        }else{
            $newCate[] = $v;
        }
    }
    return $newCate;
}
/**
 *redis哨兵模式下自动获取主服务
 */
function sentinelPort(){
    try{
        $redis = new \Redis();
        $fileurl = Env::get('config_path') . 'redis.php';
  		$config = Rconfig::load($fileurl,'redis');
        $host = $config['host'];
      	$port = $config['port'];
      	$password = $config['password'];
        $redis->connect($host, $port);
        $redis->auth($password); 
        $redis->ping(); 
        $redisInfo = $redis->info();
        if(is_array($redisInfo)&&isset($redisInfo['role'])&&$redisInfo['role']=='master'){
            return $redis;
        }else{
          	throw new \Exception($port.'是从服务器');
        }
    }catch(\Exception $e){
      	write_error_log($e);
        $portArr = [6379,6380,6381];//端口号
      	$portArr = array_flip($portArr);
      	unset($portArr[$port]);
      	$newPort = array_rand($portArr,1);
      	if(setconfig('redis.php',['port'],[$newPort])){
           return sentinelPort();
        }
    }
}
/**
*Thinkphp5.1下动态更改配置文件
*/
function setconfig($file,$pat, $rep){
    /**
     * 原理就是 打开config配置文件 然后使用正则查找替换 然后在保存文件.
     * 传递的参数为2个数组 前面的为配置 后面的为数值.  正则的匹配为单引号  如果你的是分号 请自行修改为分号
     * $pat[0] = 参数前缀;  例:   default_return_type
       $rep[0] = 要替换的内容;    例:  json
     */

    if (is_array($pat) and is_array($rep)) {
        for ($i = 0; $i < count($pat); $i++) {
            $pats[$i] = '/\'' . $pat[$i] . '\'(.*?),/';
            $reps[$i] = "'". $pat[$i]. "'". "=>" . "'".$rep[$i] ."',";
        }
        $fileurl = Env::get('config_path') . $file;
        $string = file_get_contents($fileurl); //加载配置文件
        $string = preg_replace($pats, $reps, $string); // 正则查找然后替换
        file_put_contents($fileurl, $string); // 写入配置文件
        return true;
    } else {
        return flase;
    }
}
/**
  * @param $index 索引名称
  * @param $str 检索内容
  * @param $page 分页页码
  * @param $page_size 分页大小
 */
function sphinxSearch($index,$str,$page,$perpage){
  $sphinx = new SphinxClient();
  $sphinx->SetServer('127.0.0.1', 9312);
  $sphinx->SetConnectTimeout(3);
  $sphinx->_limit = 10000;
  $sphinx->SetGroupBy('create_time', SPH_GROUPBY_ATTR, 'create_time desc');
  $res = $sphinx->Query($str,$index);
  $sphinx->Close();
  if(isset($res['matches'])&&!empty($res['matches'])){
    $ids = array_keys($res['matches']);
    $ids = array_slice($ids,($page-1)*$perpage,$perpage);
    $ids = implode(',', $ids);
    return array('ids'=>$ids,'count'=>$res['total_found']);
  }else{
    return false;
  }
}
function setHomeForumList($info){
    //连接redis
    $redis = sentinelPort();
    $redis->zAdd('HomeForumList_1',0,$info['id']);
    $min = strtotime(date('Y-m-d'));
    $time = strtotime($info['create_time'])-$min;
    //分享
    if($info['type']==1){
        $redis->zAdd('HomeForumList_2',$time,$info['id']);
    }
    //讨论
    if($info['type']==2){
        $redis->zAdd('HomeForumList_3',$time,$info['id']);
    }
    //最新
    $redis->zAdd('HomeForumList_4',$time,$info['id']);
    //推荐
    if($info['recomd']==2){
        $redis->zAdd('HomeForumList_5',$time,$info['id']);
    }
    //hash缓存（数据库数据）
    $redis->hSet('HomeForumList',$info['id'],json_encode($info));
}
function getCreateTimeAttr($date){
    $str = ''; 
    $timer = strtotime($date); 
    $diff = $_SERVER['REQUEST_TIME'] - $timer; 
    $day = floor($diff / 86400); 
    $free = $diff % 86400; 
    if($day > 0) { 
        return $day."天前"; 
    }else{ 
        if($free>0){ 
            $hour = floor($free / 3600); 
            $free = $free % 3600; 
                if($hour>0){ 
                    return $hour."小时前"; 
                }else{ 
                    if($free>0){ 
                        $min = floor($free / 60); 
                        $free = $free % 60; 
                        if($min>0){ 
                            return $min."分钟前"; 
                        }else{ 
                            if($free>0){ 
                                return $free."秒前"; 
                            }else{ 
                                return '刚刚'; 
                            } 
                       } 
                    }else{ 
                        return '刚刚'; 
                    } 
               } 
       }else{ 
           return '刚刚'; 
       } 
    } 
}

