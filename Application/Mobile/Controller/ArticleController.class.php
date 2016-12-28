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
namespace Mobile\Controller;

use Think\Controller;

class ArticleController extends MobileBaseController
{
    public function index()
    {
        $article_id = I('article_id', 38);
        $article    = D('article')->where("article_id=$article_id")->find();
        $this->assign('article', $article);
        $this->display();
    }

    /**
     * 文章内列表页
     */
    public function articleList()
    {
        $list = M('Article')->where("cat_id IN(1,2,3,4,5,6,7)")->select();
        $this->assign('list', $list);
        $this->display();
    }
    /**
     * 文章内容页
     */
    public function article()
    {
        $article_id = I('article_id', 1);
        $article    = D('article')->where("article_id=$article_id and is_open = 1")->find();
        if (empty($article)) {
            $this->error('文章不存在！');
        }
        $article['content'] = htmlspecialchars_decode($article['content']);
        $this->assign('article', $article);
        $this->display();
    }
}
