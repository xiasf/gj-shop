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
namespace Admin\Controller;

use Think\AjaxPage;

class SellerController extends BaseController
{

    public function unbundlingUser()
    {
        $seller_id = I('get.seller_id/d');
        M('seller')->where(['id' => $seller_id])->save(['bind_uid' => 0]);
        $this->success('解绑成功！');
    }

    // 为商家生成绑定用户的二维码
    public function bindUser()
    {
        $seller_id = I('get.seller_id/d');
        vendor("phpqrcode.phpqrcode");
        $data = SITE_URL . U('Mobile/User/bindUser', ['seller_id' => $seller_id]);
        // 纠错级别：L、M、Q、H
        $level = 'L';
        // 点的大小：1到10,用于手机端4就可以了
        $size = 4;
        // 下面注释了把二维码图片保存到本地的代码,如果要保存图片,用$fileName替换第二个参数false
        //$path = "images/";
        // 生成的文件名
        //$fileName = $path.$size.'.png';
        \QRcode::png($data, false, $level, $size);
    }

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
        $where = ' 1 = 1 '; //  搜索条件
        // (I('is_on_sale') !== '') && $where = "$where and is_on_sale = " . I('is_on_sale');
        // $cat_id                            = I('cat_id');
        // 关键词 搜索
        $key_word = I('key_word') ? trim(I('key_word')) : '';
        if ($key_word) {
            $where = "$where and (seller_name like '%$key_word%')";
        }

        $model = M('seller');
        $count = $model->where($where)->count();
        $Page  = new AjaxPage($count, 10);
        /**   搜索条件下 分页赋值
        foreach($condition as $key=>$val) {
        $Page->parameter[$key]   =   urlencode($val);
        }
         */
        $show      = $Page->show();
        $order_str = "`{$_POST['orderby1']}` {$_POST['orderby2']}";
        $goodsList = $model->where($where)->order($order_str)->limit($Page->firstRow . ',' . $Page->listRows)->select();

        if ($key_word != '') {
            foreach ($goodsList as $key => &$value) {
                $value['seller_name'] = str_replace($key_word, '<font color="red">' . $key_word . '</font>', $value['seller_name']);
            }
        }

        $this->assign('goodsList', $goodsList);
        $this->assign('page', $show); // 赋值分页输出
        $this->display();
    }

    public function addEditSeller()
    {
        $seller = M('seller');
        $type   = $_POST['id'] > 0 ? 2 : 1; // 标识自动验证时的 场景 1 表示插入 2 表示更新


        // if (empty($sellerInfo)) {
        //     $this->error('店铺不存在');
        // }

        // ajax提交验证
        if (IS_POST) {

            if ($type == 1) {
                // if ($seller->where(['email' => I('post.email/s')])->find()) {
                //     $this->error('邮箱被占用！');
                // }
                if ($seller->where(['mobile' => I('post.mobile/s')])->find()) {
                    $this->error('手机被占用！');
                }
            } else {
                // if ($seller->where(['id' => ['neq', I('post.id/d')], 'email' => I('post.email/s')])->find()) {
                //     $this->error('邮箱被占用！');
                // }
                if ($seller->where(['id' => ['neq', I('post.id/d')], 'mobile' => I('post.mobile/s')])->find()) {
                    $this->error('手机被占用！');
                }
            }

            C('TOKEN_ON', false);
            if (!$data = $seller->create(null, $type)) // 根据表单提交的POST数据创建数据对象
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

                if ($data['discount'] > 100 || $data['discount'] < 0) {
                    $this->error('折扣填写错误');
                }

                if ($data['exchange'] > 100 || $data['exchange'] < 0) {
                    $this->error('兑币抵扣填写错误');
                }

                if ($type == 2) {
                    $seller->reg_time = time();
                    $e = $seller->save(); // 写入数据到数据库
                } else {
                    $e = $insert_id = $seller->add(); // 写入数据到数据库
                }

                if ($e !== fasle) {

                } else {
                    $this->error('操作失败！');
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
        $seller = M('seller s');
        $sellerInfo = $seller->join('LEFT JOIN __USERS__ u on u.user_id = s.bind_uid')->field('s.*,u.nickname')->where(['s.id' => I('get.id/d')])->find();
// var_dump(M()->_sql());exit;
        if (empty($sellerInfo)) {
            $sellerInfo['province'] = 24022;
            $sellerInfo['city'] = 25086;
            $sellerInfo['district'] = 25088;
        }

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
