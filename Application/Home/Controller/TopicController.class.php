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
 * $Author: 广佳 2016-11-26 $
 */ 
namespace Home\Controller;

class TopicController extends BaseController {
	/*
	 * 专题列表
	 */
	public function topicList(){
		$topicList = M('topic')->where("topic_state=2")->select();
		$this->assign('topicList',$topicList);
		$this->display();
	}
	
	/*
	 * 专题详情
	 */
	public function detail(){
		$topic_id = I('topic_id',1);
		$topic = D('topic')->where("topic_id=$topic_id")->find();
		$this->assign('topic',$topic);
		$this->display();
	}
	
	public function info(){
		$topic_id = I('topic_id',1);
		$topic = D('topic')->where("topic_id=$topic_id")->find();                
        echo htmlspecialchars_decode($topic['topic_content']);                
        exit;
	}
}