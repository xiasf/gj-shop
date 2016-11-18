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
 * $Author: IT宇宙人 2015-08-10 $
 */
namespace Mobile\Controller;

class CartController extends MobileBaseController
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

        /**
     * 将商品加入购物车
     */
    public function addCart()
    {
        $goods_id   = I("goods_id"); // 商品id
        $goods_num  = I("goods_num"); // 商品数量
        $goods_spec = I("goods_spec"); // 商品规格
        $goods_spec = json_decode($goods_spec, true); //app 端 json 形式传输过来
        $unique_id  = I("unique_id"); // 唯一id  类似于 pc 端的session id
        $user_id    = I("user_id", 0); // 用户id
        $result     = $this->cartLogic->addCart($goods_id, $goods_num, $goods_spec, $unique_id, $user_id); // 将商品加入购物车
        exit(json_encode($result));
    }

    /**
     * ajax 将商品加入购物车
     */
    public function ajaxAddCart()
    {
        $goods_id   = I("goods_id"); // 商品id
        $goods_num  = I("goods_num"); // 商品数量
        $goods_spec = I("goods_spec"); // 商品规格
        $result     = $this->cartLogic->addCart($goods_id, $goods_num, $goods_spec, $this->session_id, $this->user_id); // 将商品加入购物车
        exit(json_encode($result));
    }

    /*
     * 请求获取购物车列表
     */
    public function cartList()
    {
        $cart_form_data = $_POST["cart_form_data"]; // goods_num 购物车商品数量
        $cart_form_data = json_decode($cart_form_data, true); //app 端 json 形式传输过来

        $unique_id         = I("unique_id"); // 唯一id  类似于 pc 端的session id
        $user_id           = I("user_id"); // 用户id
        $where             = " session_id = '$unique_id' "; // 默认按照 $unique_id 查询
        $user_id && $where = " user_id = " . $user_id; // 如果这个用户已经等了则按照用户id查询
        $cartList          = M('Cart')->where($where)->getField("id,goods_num,selected");

        if ($cart_form_data) {
            // 修改购物车数量 和勾选状态
            foreach ($cart_form_data as $key => $val) {
                $data['goods_num'] = $val['goodsNum'];
                $data['selected']  = $val['selected'];
                $cartID            = $val['cartID'];
                if (($cartList[$cartID]['goods_num'] != $data['goods_num']) || ($cartList[$cartID]['selected'] != $data['selected'])) {
                    M('Cart')->where("id = $cartID")->save($data);
                }

            }
            //$this->assign('select_all', $_POST['select_all']); // 全选框
        }

        $result = $this->cartLogic->cartList($this->user, $unique_id, 0);
        exit(json_encode($result));
    }


    /*
     * 购物车（管理）页面
     */
    public function cart()
    {
        $this->display('cart');
    }


    /**
     * 购物车第二步（结算）确定页面
     */
    public function cart2()
    {
        if ($this->user_id == 0) {
            $this->error('请先登陆', U('Mobile/User/login'));
        }

        // 当前购物车必须有选中的商品
        if ($this->cartLogic->cart_count($this->user_id, 1) == 0) {
            $this->error('你的购物车没有选中商品', 'Cart/cart');
        }

        // 有自带地址收货id的情况
        $address_id = I('address_id');
        if ($address_id) {
            $address = M('user_address')->where("address_id = $address_id")->find();
        } else {
            $address = M('user_address')->where("user_id = $this->user_id and is_default=1")->find();
        }

        // 必须设置一个收货地址
        if (empty($address)) {
            $this->redirect('Mobile/User/add_address', ['source' => 'cart2']);
        } else {
            $this->assign('address', $address);
        }

        $result       = $this->cartLogic->cartList($this->user, $this->session_id, 1, 1);       // 获取购物车商品（计算购物车）
        $shippingList = M('Plugin')->where("`type` = 'shipping' and status = 1")->select();     // 物流公司列表

        // $Model      = new \Think\Model(); // 找出这个用户的优惠券 没过期的  并且 订单金额达到 condition 优惠券指定标准的
        // $sql        = "select c1.name,c1.money,c1.condition, c2.* from __PREFIX__coupon as c1 inner join __PREFIX__coupon_list as c2  on c2.cid = c1.id and c1.type in(0,1,2,3) and order_id = 0  where c2.uid = {$this->user_id} and " . time() . " < c1.use_end_time and c1.condition <= {$result['total_price']['total_fee']}";
        // $couponList = $Model->query($sql);

        // 找出这个用户的优惠券 没过期的  并且 订单金额达到 condition 优惠券指定标准的
        // 这个优惠券是网站优惠券，而非店铺优惠券
        $couponList = M('coupon as c1')
                        ->join('INNER JOIN __COUPON_LIST__ as c2 on c2.cid = c1.id')
                        ->field('c1.name,c1.money,c1.condition,c2.*')
                        ->where(['c1.type' => ['in', '0,1,2,3']])
                        ->where(['c2.uid' => $this->user_id, 'order_id' => 0])
                        ->where(['c1.use_end_time' => ['gt', NOW_TIME]])
                        ->where(['c1.condition' => ['elt', $result['total_price']['total_fee']]])
                        ->select();

        $this->assign('couponList', $couponList);               // 优惠券列表
        $this->assign('shippingList', $shippingList);           // 物流公司列表
        $this->assign('cartList', $result['cartList']);         // 购物车的商品
        $this->assign('total_price', $result['total_price']);   // 总计
        $this->display();
    }


    /**
     * ajax 获取订单商品价格 或者提交 订单
     */
    public function cart3()
    {
        if (!IS_POST) return;

        if ($this->user_id == 0) {
            exit(json_encode(array('status' => -100, 'msg' => "登录超时请重新登录!", 'result' => null)));
        }
        // echo '<pre>';
        // print_r(I('post.'));
        // echo '</pre>';
        // exit;
        $address_id       = I("address_id/d");              // 收货地址id
        $shipping_code    = I("shipping_code/a");           // 物流编号
        $invoice_title    = I('invoice_title/a');           // 发票抬头
        $note             = I('note/a');                    // 订单备注
        // $couponTypeSelect = I("couponTypeSelect/d");     // 优惠券类型  1 下拉框选择优惠券 2 输入框输入优惠券代码
        $coupon_id        = I("coupon_id/d");               // 优惠券id
        $couponCode       = I("couponCode/s");              // 优惠券代码
        $pay_points       = I("pay_points/d", 0);           // 使用积分
        $user_money       = I("user_money/d", 0);           // 使用余额

        if ($this->cartLogic->cart_count($this->user_id, 1) == 0) {
            exit(json_encode(array('status' => -2, 'msg' => '你的购物车没有选中商品', 'result' => null)));
        }
        if (!$address_id || (!$address = M('UserAddress')->where(['address_id' => $address_id])->find())) {
            exit(json_encode(array('status' => -3, 'msg' => '请先填写收货人信息', 'result' => null)));
        }
        if (!$shipping_code || !is_array($shipping_code)) {
            exit(json_encode(array('status' => -4, 'msg' => '请选择物流信息', 'result' => null)));
        }
        foreach ($shipping_code as $key => $value) {
            if (!M('Plugin')->where(['type' => 'shipping', 'code' => $value, 'status' => 1])->find()) {
                exit(json_encode(array('status' => -4, 'msg' => '物流非法', 'result' => null)));
            }
        }

        // 计算价格和优惠信息时还是采用的是单店版的
        // 这里做拆分计算也可以
        // 不过优惠券抵扣，积分抵扣，余额抵扣需要处理一下，需要均匀到每个订单（先计算出每个订单的价格，按订单间相互的比例均匀）
        // 比例保留两位小数 substr('0.86999', 0, 4) ，如果是积分则直接舍去全部小数 floor（向下取整）（这种方式是安全的，但是有个弊端，就是可能造成不能全部都用出去）
        // 换一种方案，尽最大可能全部用出去，而不考虑比例
        /*
            这个需要处理一下

            'couponFee'         => $result['result']['coupon_price'],           // 实际用了 优惠券
            'balance'           => $result['result']['user_money'],             // 实际用了 使用用户余额
            'pointsFee'         => $result['result']['integral_money'],         // 实际用了 积分支付
        */

        // 计算购物车
        $car = $this->cartLogic->cartList($this->user, $this->session_id, 1, 1);

        foreach ($car['cartList'] as &$shop) {
            // 计算出每个店铺占总体的比例（这样取两位小数会导致少算，不过并不会有问题，只是有可能[产生两位以上小数时]让用户少抵扣钱了，并不会让用户有任何损失）
            $shop['proportion'] = substr(($shop['total_fee'] / $car['total_price']['total_fee']), 0, 4);
        }

        // echo '<pre>';
        // print_r($car);
        // echo '</pre>';
        // exit;

        foreach ($car['cartList'] as $shop) {
            $seller_id          = $shop['seller_id'];
            // 计算价格
            $result = calculate_price(
                            $this->user_id,
                            $shop['item'],
                            $shipping_code[$seller_id],
                            0,
                            $address['province'],
                            $address['city'],
                            $address['district'],
                            $pay_points,
                            $user_money,
                            $coupon_id,
                            $couponCode,
                            $shop['proportion'] // 传入比例用于计算
                        );

            // 尽最大可能全部用出去，而不考虑比例（可能会出现后面的订单没有使用到抵扣）
            $pay_points_ = $result['result']['integral_money'] * tpCache('shopping.point_rate');  // 这次使用积分
            $pay_points -= $pay_points_;

            $user_money -= $result['result']['user_money'];

            // 计算出错
            if ($result['status'] < 0) {
                exit(json_encode($result));
            }

            // 这个不受多订单，比例影响，因为这个就是单个订单的活动
            $order_prom                            = get_order_promotion($result['result']['order_amount']);
            $result['result']['order_amount']      = $order_prom['order_amount'];
            $result['result']['order_prom_id']     = $order_prom['order_prom_id'];
            $result['result']['order_prom_amount'] = $order_prom['order_prom_amount'];

            $car_price[] = array(
                'seller_id'         => $seller_id,
                'seller_name'       => $shop['seller_name'],
                'item'              => $shop['item'],

                'postFee'           => $result['result']['shipping_price'],         // 物流费 （一个店一个订单，每个订单都单独收物流费哦）
                'couponFee'         => $result['result']['coupon_price'],           // 优惠券 （实际上是网站发的购物券）
                'balance'           => $result['result']['user_money'],             // 使用用户余额
                'pointsFee'         => $result['result']['integral_money'],         // 积分支付
                'payables'          => $result['result']['order_amount'],           // 应付金额
                'goodsFee'          => $result['result']['goods_price'],            // 商品价格
                'order_prom_id'     => $result['result']['order_prom_id'],          // 订单优惠活动id
                'order_prom_amount' => $result['result']['order_prom_amount'],      // 订单优惠活动优惠了多少钱
            );
        }

        // 提交订单
        if ('submit_order' == I('request.act')) {

            // 当使用优惠券代码时需要查出优惠券ID
            // 这个优惠券其实是平台发的，应该叫购物券，比如天猫购物券，优惠券是商家发的
            if (empty($coupon_id) && !empty($couponCode)) {
                $coupon_id = M('CouponList')->where("`code`='$couponCode'")->getField('id');
            }

            // 生成订单
            $result = $this->cartLogic->addOrder($this->user_id, $address_id, $shipping_code, $invoice_title, $note, $coupon_id, $car_price); // 添加订单
            exit(json_encode($result));
        }


        foreach ($car_price as $key => $value) {
            $result['postFee']           += $value['postFee'];                  // 全部订单 物流费
            $result['couponFee']         += $value['couponFee'];                // 全部订单 优惠券 抵扣
            $result['balance']           += $value['balance'];                  // 共 使用用户余额
            $result['pointsFee']         += $value['pointsFee'];                // 积分支付（全部订单 共）
            $result['payables']          += $value['payables'];                 // 全部订单 应付金额
            $result['goodsFee']          += $value['goodsFee'];                 // 全部订单 商品价格
            $result['order_prom_id']     += $value['order_prom_id'];            // 全部 订单优惠活动id
            $result['order_prom_amount'] += $value['order_prom_amount'];        // 全部 订单优惠活动优惠了多少钱
        }

        // 都没有考虑为每个商品计算商品活动的优惠
        // 也没有考虑每个店铺各自的优惠情况

        // 返回计算结果
        $return_arr = array('status' => 1, 'msg' => '计算成功', 'result' => $result); // 返回结果状态
        exit(json_encode($return_arr));
    }


    /*
     * 订单支付页面
     */
    public function cart4()
    {

        $order_id = I('order_id/s');

        // 如果有多个订单，则到待支付订单列表去
        if (false !== stripos($order_id, ',')) {
            $this->redirect('Mobile/User/add_address', ['source' => 'cart2']);
        }

        $order    = M('Order')->where(['order_id' => $order_id])->find();
        // 如果已经支付过的订单直接到订单详情页面
        if ($order['pay_status'] == 1) {
            $this->redirect("Mobile/User/order_detail", ['id' => $order_id]);
        }

        $paymentList = M('Plugin')->where("`type`='payment' and status = 1 and  scene in(0,1)")->select();
        //微信浏览器
        if (strstr($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger')) {
            $paymentList = M('Plugin')->where("`type`='payment' and status = 1 and code in('weixin','cod')")->select();
        }
        $paymentList = convert_arr_key($paymentList, 'code');

        foreach ($paymentList as $key => $val) {
            $val['config_value'] = unserialize($val['config_value']);
            if ($val['config_value']['is_bank'] == 2) {
                $bankCodeList[$val['code']] = unserialize($val['bank_code']);
            }
        }

        $bank_img = include 'Application/Home/Conf/bank.php'; // 银行对应图片
        $payment  = M('Plugin')->where("`type`='payment' and status = 1")->select();
        $this->assign('paymentList', $paymentList);
        $this->assign('bank_img', $bank_img);
        $this->assign('order', $order);
        $this->assign('bankCodeList', $bankCodeList);
        $this->assign('pay_date', date('Y-m-d', strtotime("+1 day")));
        $this->display();
    }

    /*
     * ajax 请求获取购物车列表
     * ajax 修改购物车数量和勾选状态
     */
    public function ajaxCartList()
    {
        // 修改购物车数量 和勾选状态
        if (IS_POST) {
            $post_goods_num   = I("goods_num");     // goods_num 购物车商品数量
            $post_cart_select = I("cart_select");   // 购物车选中状态

            if ($this->user_id) {
                $where = "user_id = " . $this->user_id; // 如果这个用户已经登录了则按照用户id查询
            } else {
                $where = "session_id = '$this->session_id'"; // 默认按照 session_id 查询
            }
            $cartList = M('Cart')->where($where)->getField("id,goods_num,selected,prom_type,prom_id");

            if (is_array($post_goods_num) && $cartList) {
                foreach ($post_goods_num as $key => $goods_num) {
                    $data = [];

                    // 数量不能非法
                    $data['goods_num'] = ($goods_num < 1) ? 1 : $goods_num;
                    // 限时抢购 不能超过购买数量
                    if ($cartList[$key]['prom_type'] == 1) {
                        $flash_sale        = M('flash_sale')->where("id = {$cartList[$key]['prom_id']}")->find();
                        $data['goods_num'] = ($data['goods_num'] > $flash_sale['buy_limit']) ? $flash_sale['buy_limit'] : $data['goods_num'];
                    }

                    $data['selected'] = !empty($post_cart_select[$key]) ? 1 : 0;    // 默认为非选中状态

                    if ($cartList[$key]['goods_num'] == $data['goods_num']) {
                        unset($data['goods_num']);
                    }

                    if ($cartList[$key]['selected'] == $data['selected']) {
                        unset($data['selected']);
                    }

                    if ($data) {
                        M('Cart')->where("id = $key")->save($data);
                    }

                    // if (($cartList[$key]['goods_num'] != $data['goods_num']) || ($cartList[$key]['selected'] != $data['selected'])) {
                    //     M('Cart')->where("id = $key")->save($data);
                    // }
                }
            }

            // $this->assign('select_all', $_POST['select_all']); // 全选框
        }

        // 计算购物车
        $result = $this->cartLogic->cartList($this->user, $this->session_id, 1, 1);

        // if (empty($result['total_price'])) {
        //     $result['total_price'] = array('total_fee' => 0, 'cut_fee' => 0, 'num' => 0, 'atotal_fee' => 0, 'acut_fee' => 0, 'anum' => 0);
        // }

        $this->assign('cartList', $result['cartList']);         // 购物车的商品
        $this->assign('total_price', $result['total_price']);   // 总计
        $this->display('ajax_cart_list');
    }

    /*
     * ajax 获取用户收货地址 用于购物车确认订单页面
     */
    public function ajaxAddress()
    {

        $regionList = M('Region')->getField('id,name');

        $address_list = M('UserAddress')->where("user_id = {$this->user_id}")->select();
        $c            = M('UserAddress')->where("user_id = {$this->user_id} and is_default = 1")->count(); // 看看有没默认收货地址
        if ((count($address_list) > 0) && ($c == 0)) // 如果没有设置默认收货地址, 则第一条设置为默认收货地址
        {
            $address_list[0]['is_default'] = 1;
        }

        $this->assign('regionList', $regionList);
        $this->assign('address_list', $address_list);
        $this->display('ajax_address');
    }

    /**
     * ajax 删除购物车的商品
     */
    public function ajaxDelCart()
    {
        $ids        = I("ids"); // 商品 ids
        $result     = M("Cart")->where(" id in ($ids)")->delete(); // 删除id为5的用户数据
        $return_arr = array('status' => 1, 'msg' => '删除成功', 'result' =>
            ''); // 返回结果状态
        exit(json_encode($return_arr));
    }
}
