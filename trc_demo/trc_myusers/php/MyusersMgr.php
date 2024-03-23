<?php
namespace app\plugins\controller\myusers;

use think\Request;

use app\common\FrmController;
use app\model\Terminal;
use app\plugins\controller\myusers\Myusers;

class MyusersMgr extends FrmController
{
    public function getUserList(Request $request)
    {
		$terid = $request->param('objid');
		$model = new Terminal();
		$tobj = $model->getByUuid($terid);
		
		$model = new Myusers();
		$users = $model->getMyusers($tobj->id);
		foreach($users as $one)
		{
			$one->hidden(['id', 'terminal_id']);
		}
        return json(['status' => 0, 'message' => '', 'result' => $users]);
    }
}
