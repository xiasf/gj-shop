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
 * Date: 2016-11-26
 */
namespace Admin\Controller;

use Think\AjaxPage;

class ExchangeController extends BaseController
{
    /**----------------------------------------------*/
    /*                兑币控制器                   */
    /**----------------------------------------------*/



    public function order_list()
    {
        $map = [];
        $this->keywords = I('get.keywords');
        $map['name'] = ['like', '%' . $this->keywords . '%'];
        $count = M('union_order')->where($map)->count();
        $Page  = new \Think\Page($count, 10);
        $show  = $Page->show();
        $lists = M('union_order o')->join('LEFT JOIN __USERS__ u ON u.user_id = o.user_id')->field('o.*,u.nickname')->where($map)->order('order_id desc')->limit($Page->firstRow . ',' . $Page->listRows)->select();
        if ($this->keywords != '') {
            foreach ($lists as $key => &$value) {
                $value['name'] = str_replace($this->keywords, '<font color="red">' . $this->keywords . '</font>', $value['name']);
            }
        }
        $this->assign('lists', $lists);
        $this->assign('page', $show); // 赋值分页输出
        $this->display();
    }


    /*
     * 兑币类型列表
     */
    public function index()
    {
        //获取兑币列表
        $count = M('exchange')->count();
        $Page  = new \Think\Page($count, 10);
        $show  = $Page->show();
        $lists = M('exchange')->order('add_time desc')->limit($Page->firstRow . ',' . $Page->listRows)->select();
        $this->assign('lists', $lists);
        $this->assign('page', $show); // 赋值分页输出
        $this->display();
    }

    /*
     * 添加编辑一个兑币卡模板
     */
    public function exchange_info()
    {
        if (IS_POST) {
            set_time_limit(0);
            ini_set('memory_limit','-1');
            $data                 = I('post.');
            $data['use_start_time'] = strtotime($data['use_start_time']);
            $data['use_end_time'] = strtotime($data['use_end_time']);
            if ($data['use_end_time'] < time() + 3600) {
                $this->error('结束日期不能太近');
            }
            if (!$data['createnum'] > 0) {
                $this->error("发放数量不能小于0");
            }
            if ($data['createnum'] > 100000) {
                $this->error('每次发放数量不能超过100000张');
            }
            if ($data['money'] <= 0) {
                $this->error("每张面额不能小于0");
            }
            $data['add_time'] = time();
            $new_id           = $row           = M('exchange')->add($data);
            if (!$row) {
                $this->error('发放失败');
            }
            $add['eid']  = $new_id;
            for ($i = 0; $i < $data['createnum']; $i++) {
                do {
                    // $code        = strtolower(get_rand_str(8, 0, 1)); // 获取随机8位字符串
                    // 零填充导致Excel显示数字不正确
                    $code = zerofill(mt_rand(0, 99999999), 8, 9);
                    $password    = mt_rand(1000, 9999); // 获取随机4位数字
                    $check_exist = M('exchange_list')->where(array('code' => $code))->find();
                } while ($check_exist);
                $add['code']     = $code;
                $add['password'] = $password;
                $newId = M('exchange_list')->add($add);
                if (!$newId) {
                    exit('重复！');
                } else {
                }
            }
            adminLog("发放" . $data['createnum'] . '张' . $data['name']);

            $this->success('发送成功', U('Admin/exchange/index'));
            exit;
        }
        $def['use_start_time'] = time();
        $def['use_end_time'] = strtotime("+2 month");
        $this->assign('exchange', $def);
        $this->display();
    }



    /*
     * 兑币详细查看
     */
    public function exchange_list()
    {
        //获取兑币ID
        $eid = I('get.id');
        $status = I('get.status', 2);
        if ($status == 2) {
            $s = '';
        } elseif ($status == 1) {
            $s = ' and l.status = 1';
        } elseif ($status == 0) {
            $s = ' and l.status = 0';
        }
        //查询是否存在兑币
        $check_exchange = M('exchange')->field('id')->where(array('id' => $eid))->find();
        if (!$check_exchange['id'] > 0) {
            $this->error('不存在该类型兑币');
        }

        //查询该兑币的列表的数量
        $sql = "SELECT count(1) as c FROM __PREFIX__exchange_list  l " .
        "LEFT JOIN __PREFIX__exchange c ON c.id = l.eid " . //联合兑币表查询名称
        "LEFT JOIN __PREFIX__users u ON u.user_id = l.uid WHERE l.eid = " . $eid . $s; //联合用户表去查询用户名

        $count = M()->query($sql);
        $count = $count[0]['c'];
        $Page  = new \Think\Page($count, 10);
        $show  = $Page->show();

        //查询该兑币的列表
        $sql = "SELECT l.*,c.name,c.money,c.add_time,c.use_end_time,u.nickname FROM __PREFIX__exchange_list  l " .
        "LEFT JOIN __PREFIX__exchange c ON c.id = l.eid " . //联合兑币表查询名称
        "LEFT JOIN __PREFIX__users u ON u.user_id = l.uid WHERE l.eid = " . $eid . $s . //联合用户表去查询用户名
        " limit {$Page->firstRow} , {$Page->listRows}";// order by `l`.`id` desc
        $exchange_list = M()->query($sql);
        $this->assign('exchange_type', C('exchange_TYPE'));
        $this->assign('lists', $exchange_list);
        $this->assign('eid', $eid);
        $this->assign('status', $status);
        $this->assign('page', $show);
        $this->display();
    }


    /**
     * 导出兑币
     * @return [type] [description]
     */
    public function export_exchange()
    {

        set_time_limit(0);
        ini_set('memory_limit','-1');

        $eid = I('get.id');

        // 搜索条件
        $where = 'where 1=1 ';

        $where += ' and eid = '. $eid;

        // $sql = "select * from __EXCHANGE_LIST__ $where order by id asc";

        $sql = "SELECT l.*,c.name,c.money,c.add_time,c.use_end_time,u.nickname FROM __PREFIX__exchange_list  l " .
        "LEFT JOIN __PREFIX__exchange c ON c.id = l.eid " . //联合兑币表查询名称
        "LEFT JOIN __PREFIX__users u ON u.user_id = l.uid WHERE l.eid = " . $eid; //联合用户表去查询用户名

        $list = D()->query($sql);
        $strTable = '<table width="500" border="1">';
        $strTable .= '<tr>';
        $strTable .= '<td style="text-align:center;font-size:12px;width:120px;">ID</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="100">发放时间</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">兑币名称</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">面值</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">使用会员</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">使用时间</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">兑币码</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">卡密</td>';
        $strTable .= '</tr>';
        if(is_array($list)){
            foreach($list as $k=>$val){
                $use_time = $val['use_time'];
                $use_time = !empty($use_time) ? date('Y-m-d H:s:s', $val['use_time']) : '-';
                $strTable .= '<tr>';
                $strTable .= '<td style="text-align:center;font-size:12px;">&nbsp;'.$val['id'].'</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.date('Y-m-d H:s:s', $val['add_time']).' </td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['name'].' </td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['money'].' </td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['nickname'].' </td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$use_time.' </td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['code'].' </td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['password'].' </td>';
                $strTable .= '</tr>';
            }
        }
        $strTable .= '</table>';
        unset($list);
        downloadExcel($strTable, 'exchange');
        exit();
    }


}
