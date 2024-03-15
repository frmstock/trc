<?php
namespace app\manage\controller;

use think\Request;

use app\common\FrmController;
use app\model\Myusers;
use app\model\Terminal;

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
