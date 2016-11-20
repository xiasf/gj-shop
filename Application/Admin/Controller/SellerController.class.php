<?php
/**
 * tpshop
 * ============================================================================
 * 版权所有 2015-2027 深圳搜豹网络科技有限公司，并保留所有权利。
 * 网站地址: http://www.tp-shop.cn
 * ----------------------------------------------------------------------------
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * ============================================================================
 * Author: IT宇宙人
 * Date: 2015-09-09
 */
namespace Admin\Controller;

use Think\AjaxPage;

class SellerController extends BaseController
{
    public function sellerList()
    {
        $model      = M('seller');
        $where      = '1 = 1';
        $count      = $model->where($where)->count();
        $Page       = new AjaxPage($count, 30);
        $show       = $Page->show();
        $sellerList = $model->where($where)->order('id desc')->limit($Page->firstRow . ',' . $Page->listRows)->select();

        $this->assign('sellerList', $sellerList);
        $this->display();
    }


    public function addEditSeller()
    {
        $model = M('seller');
        $type  = $_POST['goods_id'] > 0 ? 2 : 1; // 标识自动验证时的 场景 1 表示插入 2 表示更新
        //ajax提交验证
        if (($_GET['is_ajax'] == 1) && IS_POST) {
            C('TOKEN_ON', false);
            if (!$Goods->create(null, $type)) // 根据表单提交的POST数据创建数据对象
            {
                //  编辑
                $error      = $Goods->getError();
                $error_msg  = array_values($error);
                $return_arr = array(
                    'status' => -1,
                    'msg'    => $error_msg[0],
                    'data'   => $error,
                );
                $this->ajaxReturn(json_encode($return_arr));
            } else {
                //  form表单提交
                // C('TOKEN_ON',true);
                $Goods->on_time = time(); // 上架时间
                //$Goods->cat_id = $_POST['cat_id_1'];
                $_POST['cat_id_2'] && ($Goods->cat_id = $_POST['cat_id_2']);
                $_POST['cat_id_3'] && ($Goods->cat_id = $_POST['cat_id_3']);

                $_POST['extend_cat_id_2'] && ($Goods->extend_cat_id = $_POST['extend_cat_id_2']);
                $_POST['extend_cat_id_3'] && ($Goods->extend_cat_id = $_POST['extend_cat_id_3']);
                $Goods->shipping_area_ids = implode(',', $_POST['shipping_area_ids']);
                $Goods->shipping_area_ids = $Goods->shipping_area_ids ? $Goods->shipping_area_ids : '';

                if ($type == 2) {
                    $goods_id = $_POST['goods_id'];
                    $Goods->save(); // 写入数据到数据库
                    // 修改商品后购物车的商品价格也修改一下
                    M('cart')->where("goods_id = $goods_id and spec_key = ''")->save(array(
                        'market_price'       => $_POST['market_price'], //市场价
                        'goods_price'        => $_POST['shop_price'], // 本店价
                        'member_goods_price' => $_POST['shop_price'], // 会员折扣价
                    ));
                    $Goods->afterSave($goods_id);
                } else {
                    $goods_id = $insert_id = $Goods->add(); // 写入数据到数据库
                    $Goods->afterSave($goods_id);
                }

                $GoodsLogic->saveGoodsAttr($goods_id, $_POST['goods_type']); // 处理商品 属性

                $return_arr = array(
                    'status' => 1,
                    'msg'    => '操作成功',
                    'data'   => array('url' => U('Admin/Goods/goodsList')),
                );
                $this->ajaxReturn(json_encode($return_arr));
            }
        }


        $where      = '1 = 1';
        $count      = $model->where($where)->count();
        $Page       = new AjaxPage($count, 30);
        $show       = $Page->show();
        $sellerList = $model->where($where)->order('id desc')->limit($Page->firstRow . ',' . $Page->listRows)->select();

        $this->assign('sellerList', $sellerList);
        $this->display();
    }

}
