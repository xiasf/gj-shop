<?php
/**
 * gjshop 货到付款插件
 * ============================================================================
 * 版权所有 2016-2027 湖北广佳网络科技有限公司，并保留所有权利。
 * 网站地址: http://www.gj-shop.cn
 * ----------------------------------------------------------------------------
 * 广佳微商城
 * 版权所有
 * ============================================================================
 * Author: 广佳
 * Date: 2016-11-26
 */

//namespace plugins\payment\alipay;

use Think\Model\RelationModel;
/**
 * 支付 逻辑定义
 * Class AlipayPayment
 * @package Home\Payment
 */

class cod extends RelationModel
{    
    public $tableName = 'plugin'; // 插件表            
    
    /**
     * 析构流函数
     */
    public function  __construct() {   
        parent::__construct();        
    }    
    /**
     * 生成支付代码
     * @param   array   $order      订单信息
     * @param   array   $config_value    支付方式信息
     */
    function get_code($order, $config_value)
    {       
            //header("Location:".U('/Home/User/order_detail',array('id'=>$order['order_id'])));
            //exit();
            $url = SITE_URL.U('Payment/returnUrl',array('pay_code'=>'cod','order_sn'=>$order['order_sn']));
            return "<script>location.href='".$url."';</script>";         
    }         
    
    /**
     * 页面跳转响应操作给支付接口方调用
     */
    function respond2()
    {                  
        return array('status'=>1,'order_sn'=>$_REQUEST['order_sn']);
    }
    
    
}