<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2018 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------

// +----------------------------------------------------------------------
// | 文件设置
// +----------------------------------------------------------------------
return [
    'type' => [
        'image' => [
            'size' => 5242880,//5M
            'ext' => 'jpeg,jpg,gif,bmp,png',
            'water' => [
                'status' => false,//默认不开起水印
                'type' => 'image',//默认图片水印
                'image' => [
                    'file' => './water/image/water.png',
                    'position' => 7,//默认位置左下角
                    'opacity' => 100,//透明度1-100
                ],
                'font' => [
                    'file' => 'public/water/font/font.otf',
                    'text' => '观海听潮',
                    'size' => 15,//文字大小
                    'color' => '#ffffff'//文字颜色
                ],
            ],
            'thumb' => [
                'status' => false,//默认不生成缩略图
                'width' => 100,
                'height' => 100,
                'type' => 1,//等比例缩放
            ]
        ],
        'file' => [
            'size' => 10485760,//10M
            'ext' => 'doc,docx,xls,xlsx,ppt,pptx,pdf,wps,txt,rar,zip,gz,bz2,7z',
        ]
    ],//文件类型
    'qiniu' => [
        'access_key' => '',
        'secret_key' => '',
        'bucket' => 'blog',//文件空间名称
        'domain_name' => 'qiniu.chengzhigang.cn'
    ]
];
