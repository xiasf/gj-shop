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

namespace Home\Logic;

use Think\Model\RelationModel;

/**
 * 文章模板逻辑
 * Class ArticleLogic
 * @package Home\Logic
 */

class ArticleLogic extends RelationModel
{
    public function getArticle()
    {

    }

    public function getSiteArticle()
    {
        $syscate = M('ArticleCat')->where("cat_type  = 1")->select();
        foreach ($syscate as $v) {
            $cats .= $v['cat_id'] . ',';
        }
        $cats   = trim($cats, ',');
        $result = M('Article')->where("cat_id  in ($cats)")->select();
        foreach ($result as $val) {
            $arr[$val['cat_id']][] = $val;
        }

        foreach ($syscate as $v) {
            $v['article'] = $arr[$v['cat_id']];
            $brr[]        = $v;
        }
        return $brr;
    }

    public function getArticleDetail($article_id)
    {
        $article = '';
        return $article;
    }
}
