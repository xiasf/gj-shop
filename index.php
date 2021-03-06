<?php
/**
 * gjshop
 * ============================================================================
 * 版权所有 2016-2027 湖北广佳网络科技有限公司，并保留所有权利。
 * 网站地址: http://www.gj-shop.cn
 * ----------------------------------------------------------------------------
 * 广佳微商城
 * 版权所有
 * ============================================================================
 * $Author: 广佳 $
 * $Id: index.php   2016-11-26  广佳 $
 */

// 应用入口文件
if (extension_loaded('zlib')) {
    ob_end_clean();
    ob_start('ob_gzhandler');
}

// error_reporting(E_ALL ^ E_NOTICE);//显示除去 E_NOTICE 之外的所有错误信息
// 开启调试模式 建议开发阶段开启 部署阶段注释或者设为false
define('APP_DEBUG', true);
// 定义应用目录
define('APP_PATH', './Application/');
//  定义插件目录
define('PLUGIN_PATH', 'plugins/');
// 开启页面gzip压缩
// ob_end_clean();
// define ( "GZIP_ENABLE", function_exists ( 'ob_gzhandler' ) );
// ob_start ( GZIP_ENABLE ? 'ob_gzhandler' : null );

define('UPLOAD_PATH', 'Public/upload/'); // 编辑器图片上传路径
define('GJSHOP_CACHE_TIME', 1); // gjshop 缓存时间  31104000
define('SITE_URL', 'http://' . $_SERVER['HTTP_HOST']); // 网站域名
define('HTML_PATH', './Application/Runtime/Html/'); //静态缓存文件目录，HTML_PATH可任意设置，此处设为当前项目下新建的html目录

// 引入ThinkPHP入口文件
require './ThinkPHP/ThinkPHP.php';

/*
//系统常量定义
//去THinkPHP手册中进行查找
echo "<br>"."网站的根目录地址".__ROOT__." ";
echo "<br>"."入口文件地址".__APP__." ";
echo "<br>"."当前模块地址".__URL__." ";
echo "<br>"."当前url地址".__SELF__." ";
echo "<br>"."当前操作地址".__ACTION__." ";
echo "<br>"."当前模块的模板目录".__CURRENT__." ";
echo "<br>"."当前操作名称".ACTION_NAME." ";
echo "<br>"."当前项目目录".APP_PATH." ";
echo "<br>"."当前项目名称".APP_NAME." ";
echo "<br>"."当前项目的模板目录".APP_TMPL_PATH." ";
echo "<br>"."项目的公共文件目录".APP_PUBLIC_PATH." ";
echo "<br>"."项目的配置文件目录".CONFIG_PATH." ";
echo "<br>"."项目的公共文件目录".COMMON_PATH." ";
//自动缓存与表相关的全部信息
echo "<br>"."项目的数据文件目录".DATA_PATH." runtime下的data目录";
echo "<br>"." ".GROUP_NAME."";
echo "<br>"." ".IS_CGI."";
echo "<br>"." ".IS_WIN."";
echo "<br>"." ".LANG_SET."";
echo "<br>"." ".LOG_PATH."";
echo "<br>"." ".LANG_PATH."";
echo "<br>"." ".TMPL_PATH."";
//js放入的位置，供多个应用的公共资源
echo "<br>"." ".WEB_PUBLIC_PATH."";
 */
