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

use Home\Logic\UsersLogic;
use Think\Page;
use Think\AjaxPage;

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
        // 不需要登录的操作
        $nologin = ['index', 'getShopList', 'getSellerNum', 'shop', 'cp_order', 'jiaxiao'];
        if (!$this->user_id && !in_array(ACTION_NAME, $nologin)) {
            header("location:" . U('Mobile/User/login'));
            exit;
        }
    }

    public function index()
    {
        $this->display();
    }

    /**
     *计算某个经纬度的周围某段距离的正方形的四个点
     *
     *@param lng float 经度
     *@param lat float 纬度
     *@param distance float 该点所在圆的半径，该圆与此正方形内切，默认值为0.5千米
     *@return array 正方形的四个点的经纬度坐标
     */
    private function returnSquarePoint($lng, $lat, $distance = 500)
    {
        $dlng = 2 * asin(sin($distance / (2 * 6371)) / cos(deg2rad($lat)));
        $dlng = rad2deg($dlng);

        $dlat = $distance / 6371;
        $dlat = rad2deg($dlat);

        return array(
            'left-top'     => array('lat' => $lat + $dlat, 'lng' => $lng - $dlng),
            'right-top'    => array('lat' => $lat + $dlat, 'lng' => $lng + $dlng),
            'left-bottom'  => array('lat' => $lat - $dlat, 'lng' => $lng - $dlng),
            'right-bottom' => array('lat' => $lat - $dlat, 'lng' => $lng + $dlng),
        );
    }

    //计算两个坐标的直线距离
    private function getDistance($lat1, $lng1, $lat2, $lng2)
    {
        $earthRadius = 6378138; //近似地球半径米
        // 转换为弧度
        $lat1 = ($lat1 * pi()) / 180;
        $lng1 = ($lng1 * pi()) / 180;
        $lat2 = ($lat2 * pi()) / 180;
        $lng2 = ($lng2 * pi()) / 180;
        // 使用半正矢公式  用尺规来计算
        $calcLongitude      = $lng2 - $lng1;
        $calcLatitude       = $lat2 - $lat1;
        $stepOne            = pow(sin($calcLatitude / 2), 2) + cos($lat1) * cos($lat2) * pow(sin($calcLongitude / 2), 2);
        $stepTwo            = 2 * asin(min(1, sqrt($stepOne)));
        $calculatedDistance = $earthRadius * $stepTwo;
        return round($calculatedDistance);
    }

    public function getSellerNum()
    {
        $longitude = I('longitude');
        $latitude  = I('latitude');
        $arr = [];
        $squares = $this->returnSquarePoint($longitude, $latitude);

        $result = M()->query("select count(id) as num,category from `__PREFIX__seller` where latitude != 0 and latitude > '{$squares['right-bottom']['lat']}' and latitude < '{$squares['left-top']['lat']}' and longitude > '{$squares['left-top']['lng']}' and longitude < '{$squares['right-bottom']['lng']}' and `is_lock` = 1 and type = 1 GROUP BY category");
        foreach ($result as $value) {
            $arr[] = [$value['category'], $value['num']];
        }
        $this->AjaxReturn($arr);
    }

    public function getShopList()
    {
        $longitude = I('longitude');
        $latitude  = I('latitude');
        $cat       = I('cat/s', 'all');

        if ($cat == 'all') {
            $cat = '';
        } else {
            $cat = ' and category = ' . ((int) $cat);
        }

        $squares = $this->returnSquarePoint($longitude, $latitude);

        $result = M()->query("select count(1) from `__PREFIX__seller` where latitude != 0 and latitude > '{$squares['right-bottom']['lat']}' and latitude < '{$squares['left-top']['lat']}' and longitude > '{$squares['left-top']['lng']}' and longitude < '{$squares['right-bottom']['lng']}' and `is_lock` = 1 and type = 1 {$cat}");
        $count  = $result[0]['count'];
        $page   = new AjaxPage($count, 500);

        $limit = " limit " . $page->firstRow . ',' . $page->listRows;

        $sql = "select * from `__PREFIX__seller` where latitude != 0 and latitude > '{$squares['right-bottom']['lat']}' and latitude < '{$squares['left-top']['lat']}' and longitude > '{$squares['left-top']['lng']}' and longitude < '{$squares['right-bottom']['lng']}' and `is_lock` = 1 and type = 1 {$cat} order by is_special desc,sort asc $limit";

        $shopList = M()->query($sql);

        if ($shopList) {
            foreach ($shopList as &$value) {
                $value['distance'] = $this->getDistance($latitude, $longitude, $value['latitude'], $value['longitude']);
                $value['distance'] = round($value['distance'] / 1000, 2);

                $value['consumption'] = M('union_order')->where(['seller_id' => $value['id']])->count();

                if ($value['note']) {
                    $value['note'] = explode('|', $value['note']);
                }
            }
        }

        // 距离排序
        $shopList = list_sort_by($shopList, 'distance', 'asc');

        // 特约在前
        // $shopList = list_sort_by($shopList, 'is_special', 'desc');

        // $shopList = M('seller')->where(['type' => 1])->select();

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
        $info['count'] = M('UnionOrder')->where(['seller_id' => $info['id'], 'score' => ['exp', 'is not null']])->count();
        if ($info['note']) {
            $info['note'] = explode('|', $info['note']);
        }
        $this->assign('info', $info);
        $this->display('shop');
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
        $total    = $total_amount    = round(I("total_amount/f", 0), 2); // 支付
        $exchange = round(I("exchange/f", 0), 2); // 使用兑币

        $discount  = I("discount/d", 0);
        $exchange_ = I("exchange_/f", 0);

        $order_amount_ = I("order_amount/f", 0);

        $info = M('seller')->where(['type' => 1, 'id' => $shop_id])->find();

        if (!$info) {
            $this->error('店铺不存在！');
        }

        // 折扣变了
        if ($info['discount'] != $discount) {
            exit(json_encode(array('status' => -1, 'msg' => '商家折扣信息改变，请重新刷新页面', 'result' => null)));
        }

        // 折扣变了
        if ($info['exchange'] != $exchange_) {
            exit(json_encode(array('status' => -1, 'msg' => '商家兑币优惠信息改变，请重新刷新页面', 'result' => null)));
        }

        if ($total_amount <= 0) {
            exit(json_encode(array('status' => -2, 'msg' => '输入金额不正确', 'result' => null)));
        }

        if ($exchange > $this->user['exchange']) {
            exit(json_encode(array('status' => -1, 'msg' => '你没有那么多兑币哦', 'result' => null)));
        }

        if ($info['discount'] != 0 && $info['discount'] != 100) {
            // 这个有可能多折扣，或者少折扣，看你怎么选了
            $total_amount_ = round(($total_amount * (1 - ($info['discount'] / 100))), 2);
            // $total_amount  = $total_amount * ($info['discount'] / 100);
            // $total_amount = $total_amount - $total_amount_;
        } else {
            $total_amount_ = 0;
        }

        // 不限制
        $info['exchange'] = 100;

        // 商家可以限制最多使用
        // 不做折上折
        if ($info['exchange'] != 0 && $info['exchange'] != 100) {
            $exchange_amount_max = $total_amount * ($info['exchange'] / 100);
            // 本次最多使用兑币数
            $exchange_max = $exchange_amount_max / tpCache('shopping.exchange_rate');
        } else {
            $exchange_amount_max = 0; // 不限制
            $exchange_max        = $total_amount / tpCache('shopping.exchange_rate');
        }

        // 兑币改成支持小数
        // $exchange_max = (int) $exchange_max;

        if ($exchange) {
            // 这里要控制保证两位小数，应该不能四舍五入 待完善: 2016-12-9 16:50:18
            $pay_exchange = $exchange / tpCache('shopping.exchange_rate');
            if ($exchange_amount_max) {
                $pay_exchange = ($pay_exchange > $exchange_amount_max) ? $exchange_amount_max : $pay_exchange;
            } else {
                $pay_exchange = ($pay_exchange > $total_amount) ? $total_amount : $pay_exchange;
            }
        } else {
            $pay_exchange = 0;
        }
        unset($exchange_amount_max);

        // 兑币抵扣的金额怎么能出现小数，那就成BUG了，所以要直接取整，不要小数位（这对用户来说不会损失什么，相当于如果1兑币等于1元的话，那么当兑币抵扣1.1元时，会出现使用1.1个兑币的情况，所以要处理一下，以保证兑币是整数，这样会出现这样的情况，当系统计算得到兑币需要抵扣0.9元时，即使用户输入1个兑币来抵扣，那么也不会出现扣用户0.9个兑币的情况的，不会抵扣兑币，相当于强制让用户输入了0，不使用兑币）
        // 另外产生应付不一样的原因很多，跟价格的计算方式有关，有可能应付一样，但实际上很多东西其实都改变了，所以还是要像上面一样，尽量拍短一些关键的数据是否一致
        // 就算不提示对系统来说也没有影响，提示的原因是因为到时候用户怪罪说怎么提交时的价格，数据等和我提交前算的，看的不一样了，就这个原因而已。
        $pay_exchange_num = $pay_exchange * tpCache('shopping.exchange_rate');
        // 兑币改成支持小数
        // if (!is_int($pay_exchange_num)) {
        //     $pay_exchange_num = (int) $pay_exchange_num;
        //     $pay_exchange     = $pay_exchange_num / tpCache('shopping.exchange_rate');
        // }

        $order_amount = $total_amount - $pay_exchange;

        // 提交订单
        if ('submit_order' == I('request.act')) {

            // 应付变了（这是个好提示，不提示给用户会造成，到时候真正支付钱和用户提交时在页面看到的不一样，不过一般商品的价格不会变得这么快吧，也没有人一个页面开很久吧，所以遇到这种情况应该比较少（我就遇到过天猫下单时的这个提示），还有要考虑的一个问题就是，当某些抢购页面时，价格有变化快，相比于价格用户更在乎能不能抢到了，如果这是给用户来个提示，要他再刷新一次，那真是悲催了，所以这种情况需要慎重考虑权衡一下）
            // 只能在提交订单时哦
            if ($order_amount != $order_amount_) {
                exit(json_encode(array('status' => -1, 'msg' => '价格发生改变，请刷新页面哦', 'result' => null)));
            }

            $data = [
                'seller_id'       => $info['id'],
                'name'            => $info['seller_name'],
                'order_sn'        => date('YmdHis') . rand(1000, 9999), // 订单编号（加了唯一索引，如果很小的几率引起了重复，那么插入语句会出错）
                'user_id'         => $this->user_id, // 用户id
                'add_time'        => time(),
                'exchange'        => $pay_exchange_num,
                'exchange_money'  => $pay_exchange,
                'total_amount'    => $total,
                'discount'        => $info['discount'],
                'discount_amount' => $total_amount_,
                'order_amount'    => $order_amount,
            ];

            $order_id = M('union_order')->data($data)->add();
            if (!$order_id) {
                $return_arr = array('status' => -2, 'msg' => '支付失败', 'result' => ''); // 返回结果状态
            } else {

                if ($pay_exchange_num) {
                    // 减兑币
                    $userLogic = new UsersLogic();
                    $userLogic->update_info($this->user_id, ['exchange' => $this->user['exchange'] - $pay_exchange_num]);
                    // 记录日志
                    $data4['user_id']     = $this->user_id;
                    $data4['user_money']  = 0;
                    $data4['pay_points']  = 0;
                    $data4['exchange']    = -$pay_exchange_num;
                    $data4['change_time'] = time();
                    $data4['desc']        = '异业联盟消费兑币抵扣';
                    M("AccountLog")->add($data4);
                }

                // 兑币的钱累计给商家
                $a = M('seller')->where(['id' => $shop_id])->setInc('pay_exchange', $pay_exchange);

                $return_arr = array('status' => 1, 'msg' => ($pay_exchange_num ? '兑币抵扣成功' : '下单成功'), 'result' => $order_id); // 返回结果状态


                // 如果有微信公众号 则推送一条消息到微信
                // $user = M('users')->where("user_id = $this->user_id")->find();
                // if ($user['oauth'] == 'weixin') {
                //     $wx_user    = M('wx_user')->find();
                //     $jssdk      = new \Mobile\Logic\Jssdk($wx_user['appid'], $wx_user['appsecret']);
                //     $wx_content = "感谢您光临[{$info['seller_name']}]，祝您生活愉快！";
                //     $jssdk->push_msg($user['openid'], $wx_content);
                // }

                if ($info['bind_uid']) {
                    $sellerUser = M('users')->where("user_id = {$info['bind_uid']}")->find();
                    if ($sellerUser['oauth'] == 'weixin') {
                        $wx_user    = M('wx_user')->find();
                        $jssdk      = new \Mobile\Logic\Jssdk($wx_user['appid'], $wx_user['appsecret']);
                        $wx_content = "尊敬的商户[{$info['seller_name']}]，用户{$user['nickname']}下单成功，使用兑币：{$pay_exchange}";
                        $jssdk->push_msg($sellerUser['openid'], $wx_content);
                    }
                }

                // 给我发一条
                // $wx_user    = M('wx_user')->find();
                // $jssdk      = new \Mobile\Logic\Jssdk($wx_user['appid'], $wx_user['appsecret']);
                // $wx_content = "宝宝，商户[{$info['seller_name']}]有联盟订单了，用户{$user['nickname']}下单成功，使用兑币：{$pay_exchange}";
                // $jssdk->push_msg('oJ31qs8cbMajq_l3EMoTs_Rp1K9E', $wx_content);

            }
            exit(json_encode($return_arr));
        }

        $result                  = [];
        $result['exchange']      = $pay_exchange_num; // 兑币支付
        $result['order_amount']  = $order_amount; // 应付金额
        $result['total_amount']  = $total;        // 输入金额
        $result['total_amount_'] = $total_amount_; // 优惠了多少钱
        $result['exchange_max']  = $exchange_max; // 本次最多能使用的兑币数量

        // 都没有考虑为每个商品计算商品活动的优惠
        // 也没有考虑每个店铺各自的优惠情况

        // 返回计算结果
        $return_arr = array('status' => 1, 'msg' => '计算成功', 'result' => $result); // 返回结果状态
        exit(json_encode($return_arr));
    }

    /*
     * 订单详情页面
     */
    public function order()
    {

        $order_id = I('order_id/d');

        $order = M('UnionOrder')->where(['user_id' => $this->user_id, 'order_id' => $order_id])->find();
        if (!$order) {
            $this->error('订单不存在！');
        }

        $this->assign('order', $order);
        $this->display();
    }

    /*
     * 订单列表
     */
    public function order_list()
    {
        $where = ' user_id=' . $this->user_id;
        $count = M('UnionOrder')->where($where)->count();
        $Page  = new Page($count, 10);

        $show       = $Page->show();
        $order_str  = "order_id DESC";
        $order_list = M('UnionOrder')->order($order_str)->where($where)->limit($Page->firstRow . ',' . $Page->listRows)->select();

        $this->assign('page', $show);
        $this->assign('lists', $order_list);

        if ($_GET['is_ajax']) {
            $this->display('ajax_order_list');
            exit;
        }

        $this->display();
    }

    /*
     * 评论列表
     */
    public function comment_list()
    {
        $this->seller_id = $seller_id = I('get.seller_id/d');
        $where = 'o.score is not null and o.seller_id=' . $seller_id;
        $count = M('UnionOrder o')->where($where)->count();
        $Page  = new Page($count, 10);

        $show       = $Page->show();
        $order_str  = "order_id DESC";
        $order_list = M('UnionOrder o')->join('LEFT JOIN __USERS__ u on u.user_id = o.user_id')->field('o.*,u.head_pic,u.nickname')->order($order_str)->where($where)->limit($Page->firstRow . ',' . $Page->listRows)->select();

        $this->assign('page', $show);
        $this->assign('lists', $order_list);

        if ($_GET['is_ajax']) {
            $this->display('ajax_comment_list');
            exit;
        }

        $this->display();
    }

    /*
     * 订单评价
     */
    public function orderComment()
    {

        $order_id = I('get.order_id/d');

        $order = M('UnionOrder')->where(['user_id' => $this->user_id, 'order_id' => $order_id, 'score' => ['exp', 'is null']])->find();
        if (!$order) {
            $this->error('订单不存在或已评论！');
        }

        if (IS_POST) {
            $data['score'] = I('post.score/d');
            $data['comments'] = I('post.comments/s');
            M('UnionOrder')->where(['order_id' => $order_id])->save($data);

            // 计算评分
            $count = M('UnionOrder')->where(['seller_id' => $order['seller_id'], 'score' => ['exp', 'is not null']])->count();
            $score = M('UnionOrder')->where(['seller_id' => $order['seller_id'], 'score' => ['exp', 'is not null']])->sum('score');
            $s = round($score / $count, 1);
            // 更新店铺评分
            M('seller')->where(['id' => $order['seller_id']])->save(['score' => $s]);

            // 如果有微信公众号 则推送一条消息到微信
            $user = $this->user;
            if ($user['oauth'] == 'weixin') {
                $wx_user    = M('wx_user')->find();
                $jssdk      = new \Mobile\Logic\Jssdk($wx_user['appid'], $wx_user['appsecret']);
                $wx_content = "感谢您对[{$order['name']}]的服务作出重要的评价，谢谢！";
                $jssdk->push_msg($user['openid'], $wx_content);
            }
            $this->success('评价成功！', U('Mobile/Union/order_list'));
            exit;
        }

        $this->assign('order', $order);
        $this->display('order_comment');
    }

}
