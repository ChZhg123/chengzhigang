<?php

/*---------------------------
 * Flyヽ
 * http://lfei.org
 * 2016-02-04
 * action_delete.php
 * 删除 Ueditor 目录下的文件
 *---------------------------*/

try {
    //获取路径
    $path = $_POST['path'];
    $path = str_replace('../', '', $path);
    $path = str_replace('/', '\\', $path);
    
    //获取完整路径
    $path = $_SERVER['DOCUMENT_ROOT'].$path;
    if(file_exists($path)) {
        //删除文件
        unlink($path);
        return 'ok';
    } else {
        return '删除失败，未找到'.$path;
    }
} catch (Exception $e) {
    return '删除异常：'.$e->getMessage();
}