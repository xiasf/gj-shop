<?php
/**
 * tpshop
 * ============================================================================
 * * 版权所有 2015-2027 深圳搜豹网络科技有限公司，并保留所有权利。
 * 网站地址: http://www.tp-shop.cn
 * ----------------------------------------------------------------------------
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * ============================================================================
 * $Author: 当燃 2016-01-09
 */
namespace Mobile\Controller;

class UnionController extends MobileBaseController
{

    public $cartLogic; // 购物车逻辑操作类
    public $user_id = 0;
    public $user    = array();

    /**
     * 析构流函数
     */
    public function _initialize()
    {
        parent::_initialize();
        $this->cartLogic = new \Home\Logic\CartLogic();
        // 如果用户登录了
        if (session('?user')) {
            $user = session('user');
            $user = M('users')->where("user_id = {$user['user_id']}")->find();
            session('user', $user); //覆盖session 中的 user
            $this->user    = $user;
            $this->user_id = $user['user_id'];
            $this->assign('user', $user); //存储用户信息
            // [修改购物车] 给用户计算会员价 登录前后不一样
            if ($user) {
                // 会员折扣，默认为1不享受
                $user['discount'] = empty($user['discount']) ? 1 : $user['discount'];
                // 购物车有可能没登录时加入了商品，登陆后则不适用原先为登录的商品了
                M('Cart')->execute("update `__PREFIX__cart` set member_goods_price = goods_price * {$user['discount']} where (user_id ={$user['user_id']} or session_id = '{$this->session_id}') and prom_type = 0");
                // 并且此享受折扣价的商品还不能参加活动
            }
        }
    }

    public function index()
    {
        $this->display();
    }

    public function getShopList()
    {
        $shopList = M('seller')->where(['type' => 1])->select();

        $this->assign('list', $shopList);
        $this->display('shop_list');
    }

    public function shop()
    {
        $id = I('get.id/d');

        $info = M('seller')->where(['type' => 1, 'id' => $id])->find();

        if (!$info) {
            $this->error('店铺不存在！');
        }

        $this->assign('info', $info);
        $this->display('shop');
    }

    public function zf()
    {

        $id = I('get.id/d');

        $info = M('seller')->where(['type' => 1, 'id' => $id])->find();

        if (!$info) {
            $this->error('店铺不存在！');
        }

        $this->assign('info', $info);
        $this->display('zf');
    }

    public function cp_order()
    {
        if (!IS_POST) {
            return;
        }

        if ($this->user_id == 0) {
            exit(json_encode(array('status' => -100, 'msg' => "登录超时请重新登录!", 'result' => null)));
        }

        $shop_id  = I("shop_id/d", 0); // 支付
        $total    = $total_amount    = I("total_amount/d", 0); // 支付
        $exchange = I("exchange/d", 0); // 使用兑币

        $discount = I("discount/d", 0);
        $exchange_ = I("exchange_/d", 0);

        $info = M('seller')->where(['type' => 1, 'id' => $shop_id])->find();

        if (!$info) {
            $this->error('店铺不存在！');
        }

        // 折扣变了
        if ($info['discount'] != $discount) {
            exit(json_encode(array('status' => -1, 'msg' => '折扣信息改变，请重新刷新页面', 'result' => null)));
        }

        // 折扣变了
        if ($info['exchange'] != $exchange_) {
            exit(json_encode(array('status' => -1, 'msg' => '兑币优惠信息改变，请重新刷新页面', 'result' => null)));
        }

        if ($total_amount <= 0) {
            exit(json_encode(array('status' => -2, 'msg' => '输入金额不正确', 'result' => null)));
        }

        if ($exchange > $this->user['exchange']) {
            exit(json_encode(array('status' => -3, 'msg' => '你没有那么多兑币哦', 'result' => null)));
        }

        if ($info['discount'] != 0 && $info['discount'] != 100) {
            $total_amount_ = $total_amount * (1 - ($info['discount'] / 100));
            $total_amount  = $total_amount * ($info['discount'] / 100);
        } else {
            $total_amount_ = 0;
        }


        // 商家可以限制最多使用
        if ($info['exchange'] != 0 && $info['exchange'] != 100) {
            $exchange_amount_max = $total_amount * ($info['exchange'] / 100);
        } else {
            $exchange_amount_max = 0;// 不限制
        }


        if ($exchange) {
            $pay_exchange = $exchange / tpCache('shopping.exchange_rate');

            if ($exchange_amount_max) {
                $pay_exchange = ($pay_exchange > $exchange_amount_max) ? $exchange_amount_max : $pay_exchange;
            } else {
                $pay_exchange = ($pay_exchange > $total_amount) ? $total_amount : $pay_exchange;
            }
        } else {
            $pay_exchange = 0;
        }

        // 兑币抵扣的金额怎么能出现小数，那就成BUG了，所以要直接取整，不要小数位（这对用户来说不会损失什么，相当于如果10兑币等于1元的话，那么用户使用9兑币就相当于没有使用一样，不会有效果，相当于强制让用户输入了0）
        $pay_exchange = (int) $pay_exchange;

        $order_amount = $total_amount - $pay_exchange;
        $exchange     = $pay_exchange * tpCache('shopping.exchange_rate');

        // 提交订单
        if ('submit_order' == I('request.act')) {

            $data = [
                'seller_id'       => $info['id'],
                'name'            => $info['seller_name'],
                'order_sn'        => date('YmdHis') . rand(1000, 9999), // 订单编号（加了唯一索引，如果很小的几率引起了重复，那么插入语句会出错）
                'user_id'         => $this->user_id, // 用户id
                'add_time'        => time(),
                'exchange'        => $exchange,
                'exchange_money'  => $pay_exchange,
                'total_amount'    => $total,
                'discount'        => $info['discount'],
                'discount_amount' => $total_amount_,
                'order_amount'    => $order_amount,
            ];

            $order_id = M('union_order')->data($data)->add();
            if (!$order_id) {
                $return_arr = array('status' => -2, 'msg' => '提交失败', 'result' => ''); // 返回结果状态
            } else {
                $return_arr = array('status' => 1, 'msg' => '提交成功', 'result' => $order_id); // 返回结果状态
            }
            exit(json_encode($return_arr));
        }

        $result                  = [];
        $result['exchange']      = $exchange; // 兑币支付
        $result['order_amount']  = $order_amount; // 应付金额
        $result['total_amount_'] = $total_amount_; // 优惠了多少钱

        // 都没有考虑为每个商品计算商品活动的优惠
        // 也没有考虑每个店铺各自的优惠情况

        // 返回计算结果
        $return_arr = array('status' => 1, 'msg' => '计算成功', 'result' => $result); // 返回结果状态
        exit(json_encode($return_arr));
    }

}
