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
 * $Author: 广佳 2016-01-09
 */
namespace Mobile\Controller;

class IndexController extends MobileBaseController
{

    function test()
    {
        echo get_thum_images('/Public/upload/ad/2016/12-11/584d14dc412ac.jpg', 160, 160);
    }

    public function index()
    {
        /*
        //获取微信配置
        $wechat_list = M('wx_user')->select();
        $wechat_config = $wechat_list[0];
        $this->weixin_config = $wechat_config;
        // 微信Jssdk 操作类 用分享朋友圈 JS
        $jssdk = new \Mobile\Logic\Jssdk($this->weixin_config['appid'], $this->weixin_config['appsecret']);
        $signPackage = $jssdk->GetSignPackage();
        print_r($signPackage);
         */
        $hot_goods = M('goods')->where("is_hot=1 and is_on_sale=1")->order('goods_id DESC')->limit(20)->cache(true, GJSHOP_CACHE_TIME)->select(); //首页热卖商品
        $thems     = M('goods_category')->where('level=1')->order('sort_order')->limit(9)->cache(true, GJSHOP_CACHE_TIME)->select();
        $this->assign('thems', $thems);
        $this->assign('hot_goods', $hot_goods);
        $favourite_goods = M('goods')->where("is_recommend=1 and is_on_sale=1")->order('goods_id DESC')->limit(20)->cache(true, GJSHOP_CACHE_TIME)->select(); //首页推荐商品
        $this->assign('favourite_goods', $favourite_goods);
        $this->display();
    }


    /**
     * 分类列表显示
     */
    public function categoryList()
    {
        $this->display();
    }

    /**
     * 模板列表
     */
    public function mobanlist()
    {
        $arr = glob("D:/wamp/www/svn_gjshop/mobile--html/*.html");
        foreach ($arr as $key => $val) {
            $html = end(explode('/', $val));
            echo "<a href='http://www.php.com/svn_gjshop/mobile--html/{$html}' target='_blank'>{$html}</a> <br/>";
        }
    }

    /**
     * 商品列表页
     */
    public function goodsList()
    {
        $goodsLogic = new \Home\Logic\GoodsLogic(); // 前台商品操作逻辑类
        $id         = I('get.id', 0); // 当前分类id
        $lists      = getCatGrandson($id);
        $this->assign('lists', $lists);
        $this->display();
    }

    public function ajaxGetMore()
    {
        $p               = I('p', 1);
        $favourite_goods = M('goods')->where("is_recommend=1 and is_on_sale=1")->order('goods_id DESC')->page($p, 4)->cache(true, GJSHOP_CACHE_TIME)->select(); //首页推荐商品
        $this->assign('favourite_goods', $favourite_goods);
        $this->display();
    }
}
