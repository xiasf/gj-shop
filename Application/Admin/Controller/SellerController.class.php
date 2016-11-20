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

    /**
     *  店铺列表
     */
    public function ajaxSellerList()
    {
        $where = ' 1 = 1 '; // 搜索条件
        // (I('is_on_sale') !== '') && $where = "$where and is_on_sale = " . I('is_on_sale');
        // $cat_id                            = I('cat_id');
        // 关键词搜索
        $key_word = I('key_word') ? trim(I('key_word')) : '';
        if ($key_word) {
            $where = "$where and (seller_name like '%$key_word%')";
        }

        $model = M('seller');
        $count = $model->where($where)->count();
        $Page  = new AjaxPage($count, 10);
        /**  搜索条件下 分页赋值
        foreach($condition as $key=>$val) {
        $Page->parameter[$key]   =   urlencode($val);
        }
         */
        $show      = $Page->show();
        $order_str = "`{$_POST['orderby1']}` {$_POST['orderby2']}";
        $goodsList = $model->where($where)->order($order_str)->limit($Page->firstRow . ',' . $Page->listRows)->select();

        $this->assign('goodsList', $goodsList);
        $this->assign('page', $show); // 赋值分页输出
        $this->display();
    }

    public function addEditSeller()
    {
        $seller = M('seller');
        $type   = $_POST['id'] > 0 ? 2 : 1; // 标识自动验证时的 场景 1 表示插入 2 表示更新

        // ajax提交验证
        if (IS_POST) {
            C('TOKEN_ON', false);
            if (!$seller->create(null, $type)) // 根据表单提交的POST数据创建数据对象
            {
                //  编辑
                $error      = $seller->getError();
                $error_msg  = array_values($error);
                $return_arr = array(
                    'status' => -1,
                    'msg'    => $error_msg[0],
                    'data'   => $error,
                );
                $this->error($error_msg);
            } else {
                //  form表单提交
                // C('TOKEN_ON',true);

                if ($seller->passward) {
                    $seller->passward = md5($seller->passward);
                }

                if ($type == 2) {
                    $seller->reg_time = time();
                    $seller->save(); // 写入数据到数据库
                } else {
                    $id = $insert_id = $seller->add(); // 写入数据到数据库
                }

                $return_arr = array(
                    'status' => 1,
                    'msg'    => '操作成功',
                    'data'   => array('url' => U('Admin/Seller/sellerList')),
                );
                $this->success($return_arr['msg'], $return_arr['data']['url']);
                exit;
            }
        }

        $sellerInfo = M('seller')->where('id=' . I('GET.id/d', 0))->find();

        $province = M('region')->where(array('parent_id'=>0))->select();
        $city =  M('region')->where(array('parent_id'=>$sellerInfo['province']))->select();
        $area =  M('region')->where(array('parent_id'=>$sellerInfo['city']))->select();
        $this->assign('province',$province);
        $this->assign('city',$city);
        $this->assign('area',$area);

        $this->assign('info', $sellerInfo);
        $this->display();
    }

}
