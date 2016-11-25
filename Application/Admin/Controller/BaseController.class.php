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

use Admin\Logic\UpgradeLogic;
use Think\Controller;

class BaseController extends Controller
{

    /**
     * 析构函数
     */
    public function __construct()
    {
        parent::__construct();
        // $upgradeLogic = new UpgradeLogic();
        // $upgradeMsg   = $upgradeLogic->checkVersion(); //升级包消息
        // $this->assign('upgradeMsg', $upgradeMsg);
        //用户中心面包屑导航
        $navigate_admin = navigate_admin();
        $this->assign('navigate_admin', $navigate_admin);
        tpversion();
    }

    /*
     * 初始化操作
     */
    public function _initialize()
    {
        $this->assign('action', ACTION_NAME);
        //过滤不需要登陆的行为
        if (in_array(ACTION_NAME, array('login', 'logout', 'vertify')) || in_array(CONTROLLER_NAME, array('Ueditor', 'Uploadify'))) {
            //return;
        } else {
            if (session('admin_id') > 0) {
                $this->check_priv(); //检查管理员菜单操作权限
            } else {
                $this->error('请先登陆', U('Admin/Admin/login'), 1);
            }
        }
        $this->public_assign();
    }

    /**
     * 保存公告变量到 smarty中 比如 导航
     */
    public function public_assign()
    {
        $gjshop_config = array();
        $tp_config     = M('config')->select();
        foreach ($tp_config as $k => $v) {
            $gjshop_config[$v['inc_type'] . '_' . $v['name']] = $v['value'];
        }
        $this->assign('gjshop_config', $gjshop_config);
    }

    public function check_priv()
    {
        $ctl      = CONTROLLER_NAME;
        $act      = ACTION_NAME;
        $act_list = session('act_list');
        //无需验证的操作
        $uneed_check = array('login', 'logout', 'vertifyHandle', 'vertify', 'imageUp', 'upload', 'login_task');
        if ($ctl == 'Index' || $act_list == 'all') {
            //后台首页控制器无需验证,超级管理员无需验证
            return true;
        } elseif (strpos('ajax', $act) || in_array($act, $uneed_check)) {
            //所有ajax请求不需要验证权限
            return true;
        } else {
            $right = M('system_menu')->where("id in ($act_list)")->cache(true)->getField('right', true);
            foreach ($right as $val) {
                $role_right .= $val . ',';
            }
            $role_right = explode(',', $role_right);
            //检查是否拥有此操作权限
            if (!in_array($ctl . 'Controller@' . $act, $role_right)) {
                $this->error('您没有操作权限,请联系超级管理员分配权限', U('Admin/Index/welcome'));
            }
        }
    }
}
