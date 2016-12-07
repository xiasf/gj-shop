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
 * 微信交互类
 */
namespace Home\Controller;

use Think\Controller;

class WeixinController extends BaseController
{
    public $client;
    public $wechat_config;
    public function _initialize()
    {
        parent::_initialize();
        //获取微信配置信息
        $this->wechat_config = M('wx_user')->find();
        $options             = array(
            'token'          => $this->wechat_config['w_token'], //填写你设定的key
            'encodingaeskey' => $this->wechat_config['aeskey'], //填写加密用的EncodingAESKey
            'appid'          => $this->wechat_config['appid'], //填写高级调用功能的app id
            'appsecret'      => $this->wechat_config['appsecret'], //填写高级调用功能的密钥
        );

    }

    public function oauth()
    {

    }

    public function index()
    {
        // file_put_contents(APP_PATH . 'log1.txt', 'str');
        $echoStr = $_GET["echostr"];
        if ($this->checkSignature()) {
            if ($this->wechat_config['wait_access'] == 0) {
                // 未接入则更新接入状态
                M('wx_user')->where(['id' => $this->wechat_config['id']])->save(['wait_access' => 1]);
                echo $echoStr;
                exit;
            } else {
                $this->responseMsg();
            }
        }
    }

    public function responseMsg()
    {
        //get post data, May be due to the different environments
        $postStr = $GLOBALS["HTTP_RAW_POST_DATA"];
        //extract post data
        if (empty($postStr)) {
            echo '';
            exit;
        }

        /* libxml_disable_entity_loader is to prevent XML eXternal Entity Injection,
        the best way is to check the validity of xml by yourself */
        libxml_disable_entity_loader(true);
        $postObj      = simplexml_load_string($postStr, 'SimpleXMLElement', LIBXML_NOCDATA);
        $fromUsername = $postObj->FromUserName;
        $createTime   = $postObj->CreateTime;
        $toUsername   = $postObj->ToUserName;
        $keyword      = trim($postObj->Content);
        $time         = time();

        /* 微信服务器在五秒内收不到响应会断掉连接，并且重新发起请求，总共重试三次
        关于重试的消息排重，推荐使用FromUserName + CreateTime 排重。 */
        // 消息排重
        // if (($t = S('responseMsg-' . $fromUsername)) && (($createTime - $t) < 5)) {
        //     echo '';
        //     exit;
        // }
        // S('responseMsg-' . $fromUsername, $createTime);


        //点击菜单拉取消息时的事件推送
        /*
         * 1、click：点击推事件
         * 用户点击click类型按钮后，微信服务器会通过消息接口推送消息类型为event的结构给开发者（参考消息接口指南）
         * 并且带上按钮中开发者填写的key值，开发者可以通过自定义的key值与用户进行交互；
         */
        if ($postObj->MsgType == 'event' && $postObj->Event == 'CLICK') {
            $keyword = trim($postObj->EventKey);
        }

        if (empty($keyword)) {
            // exit("Input something...");
        }

        // 图文回复
        $wx_img = M('wx_img')->where("keyword like '%$keyword%'")->find();
        if ($wx_img) {
            $textTpl = "<xml>
                        <ToUserName><![CDATA[%s]]></ToUserName>
                        <FromUserName><![CDATA[%s]]></FromUserName>
                        <CreateTime>%s</CreateTime>
                        <MsgType><![CDATA[%s]]></MsgType>
                        <ArticleCount><![CDATA[%s]]></ArticleCount>
                        <Articles>
                            <item>
                                <Title><![CDATA[%s]]></Title>
                                <Description><![CDATA[%s]]></Description>
                                <PicUrl><![CDATA[%s]]></PicUrl>
                                <Url><![CDATA[%s]]></Url>
                            </item>
                        </Articles>
                        </xml>";
            $resultStr = sprintf($textTpl, $fromUsername, $toUsername, $time, 'news', '1', $wx_img['title'], $wx_img['desc']
                , $wx_img['pic'], $wx_img['url']);
            exit($resultStr);
        }

        // 文本回复
        $wx_text = M('wx_text')->where("keyword like '%$keyword%'")->find();
        if ($wx_text) {
            $textTpl = "<xml>
                        <ToUserName><![CDATA[%s]]></ToUserName>
                        <FromUserName><![CDATA[%s]]></FromUserName>
                        <CreateTime>%s</CreateTime>
                        <MsgType><![CDATA[%s]]></MsgType>
                        <Content><![CDATA[%s]]></Content>
                        <FuncFlag>0</FuncFlag>
                        </xml>";
            $contentStr = $wx_text['text'];
            $resultStr  = sprintf($textTpl, $fromUsername, $toUsername, $time, 'text', $contentStr);
            exit($resultStr);
        }



        // 关注/取消关注事件
        if ($postObj->MsgType == 'event' && ($postObj->Event == 'subscribe' || $postObj->Event == 'unsubscribe')) {
            $subscribe = 0;
            $msgType = 'text';
            $openid = trim($postObj->FromUserName);

            if ($postObj->Event == 'subscribe') {
                $subscribe = 1;
                $contentStr = '感谢关注广佳商城!';
            } else {
                $contentStr = '人家哪里不好嘛，干嘛取消关注啊!';
            }

            $user = get_user_info($openid, 3, 'weixin');
            // 此人是商城用户
            if ($user) {

                // 他的上级（推荐者）
                $invitation = M('invitation')->where(['uid' => $user['user_id'], 'status' => 0])->find();
                // 有推荐者，并且此推荐没生效（防止刷）
                if ($invitation['leader_uid'] && ($leaderUser = get_user_info($invitation['leader_uid']))) {

                    // 这个推广状态生效
                    M('invitation')->where(['leader_uid' => $invitation['leader_uid'], 'uid' => $user['user_id']])->save(['status' => 1]);

                    // 给推荐者发放奖励
                    M('users')->where("user_id = '{$leaderUser['user_id']}'")->save(['exchange' => $leaderUser['exchange'] + $invitation['exchange']]);

                    $data4['user_id']     = $leaderUser['user_id'];
                    $data4['user_money']  = 0;
                    $data4['pay_points']  = 0;
                    $data4['exchange']    = +$invitation['exchange'];
                    $data4['change_time'] = time();
                    $data4['desc']        = '推广奖励-来自' . $user['nickname'];
                    // $data4['order_sn']    = $order['order_sn'];
                    // $data4['order_id']    = $order_id;
                    M("AccountLog")->add($data4);

                    if ($leaderUser['oauth'] == 'weixin') {file_put_contents(APP_PATH . '3.txt', 'str');
                        $wx_user    = M('wx_user')->find();
                        $jssdk      = new \Mobile\Logic\Jssdk($wx_user['appid'], $wx_user['appsecret']);
                        $wx_content = "恭喜完成推广任务奖励兑币{$invitation['exchange']}，推广奖励-来自{$user['nickname']}";
                        $jssdk->push_msg($leaderUser['openid'], $wx_content);
                    }
                }

                // 用户关注标记
                M('users')->where("user_id = '{$user['user_id']}'")->save(['subscribe' => $subscribe]);
            }

            // 其他文本回复
            $textTpl = "<xml>
                        <ToUserName><![CDATA[%s]]></ToUserName>
                        <FromUserName><![CDATA[%s]]></FromUserName>
                        <CreateTime>%s</CreateTime>
                        <MsgType><![CDATA[%s]]></MsgType>
                        <Content><![CDATA[%s]]></Content>
                        <FuncFlag>0</FuncFlag>
                        </xml>";
            $resultStr  = sprintf($textTpl, $fromUsername, $toUsername, $time, $msgType, $contentStr);
            exit($resultStr);
        }


        // 其他文本回复
        $textTpl = "<xml>
                    <ToUserName><![CDATA[%s]]></ToUserName>
                    <FromUserName><![CDATA[%s]]></FromUserName>
                    <CreateTime>%s</CreateTime>
                    <MsgType><![CDATA[%s]]></MsgType>
                    <Content><![CDATA[%s]]></Content>
                    <FuncFlag>0</FuncFlag>
                    </xml>";
        $msgType = 'text';
        $contentStr = '欢迎来到广佳商城!';
        $resultStr  = sprintf($textTpl, $fromUsername, $toUsername, $time, $msgType, $contentStr);
        exit($resultStr);
    }

    private function checkSignature()
    {
        $signature = $_GET["signature"];
        $timestamp = $_GET["timestamp"];
        $nonce     = $_GET["nonce"];

        $token  = $this->wechat_config['w_token'];
        $tmpArr = array($token, $timestamp, $nonce);
        // use SORT_STRING rule
        sort($tmpArr, SORT_STRING);
        $tmpStr = implode($tmpArr);
        $tmpStr = sha1($tmpStr);

        if ($tmpStr == $signature) {
            return true;
        } else {
            return false;
        }
    }

}
