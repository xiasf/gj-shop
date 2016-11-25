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
 * Author: 广佳
 * Date: 2016-11-26
 */

namespace Admin\Model;
use Think\Model;
class ShippingAreaModel extends Model {

    /**
     * 获取配送区域
     * @return mixed
     */
    public function getShippingArea()
    {
        $shipping_areas = M('shipping_area')->where('')->select();
        foreach($shipping_areas as $key => $val){
            $shipping_areas[$key]['config'] = unserialize($shipping_areas[$key]['config']);
        }
        return $shipping_areas;
    }

}
