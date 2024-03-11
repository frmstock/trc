<?php
namespace app\manage\controller;

use think\Exception;
use think\Request;
use think\Session;

use app\common\FrmController;
use app\model\AlertConfig;
use app\model\AlertConfigDetail;
use app\model\Terminal;

class AlertConfigMgr extends FrmController
{
    public function getList()
    {
		$entid = Session::get('entid');
		$model = new AlertConfig();
		$result = $model->getByEnterprise($entid);
		foreach($result as $one)
		{
			$one->hidden(['id', 'enterprise_id', 'terminal_id', 'terminal.id', 'alert_config_details.id', 'alert_config_details.alert_config_id']);
			/*foreach($one->alert_config_details as $item)
			{
				$item->itemstr = config('AlertConfigItem.name')[$item->item];
				$item->category = config('AlertConfigItem.category')[$item->item];
			}*/
		}
        return json(['status' => 0, 'message' => '', 'result' => $result]);
    }
	
    public function addTerminal(Request $request)
    {
		$entid = Session::get('entid');
		$tids = input('post.')['tids'];
		$model = new Terminal();
		foreach($tids as $tuuid)
		{
			$tobj = $model->getByUuid($tuuid);
			$tt = new AlertConfig();
			$tt->enterprise_id = $entid;
			$tt->terminal_id = $tobj->id;
			$tt->status = 0;
			$tt->save();
		}
        return json(['status' => 0, 'message' => '']);
    }
	
    public function delTerminal(Request $request)
    {
		$entid = Session::get('entid');
		$tid = $request->param('tid');
		
		$model = new Terminal();
		$tobj = $model->getByUuid($tid);
		
		$model = new AlertConfig();
		$count = $model->where(['enterprise_id' => $entid, 'terminal_id' => $tobj->id])->delete();
		if($count>=0)
		{
			return json(['status' => 0, 'message' => 'delete ' . $count]);
		}
		
        return json(['status' => -1, 'message' => '删除失败 ']);
	}
	
	public function addTerminalItem(Request $request)
    {
		$entid = Session::get('entid');
		$objid = $request->param('objid');
		$items = input('post.')['item'];
		
		$model = new Terminal();
		$tobj = $model->getByUuid($objid);
		
		$model = new AlertConfig();
		$aobj = $model->findOne($entid, $tobj->id);
		
		foreach(config('AlertConfigItem.items') as $item)
		{
			try
			{
				if(in_array($item, $items))
				{
					$tt = new AlertConfigDetail();
					$tt->alert_config_id = $aobj->id;
					$tt->item = $item;
					$tt->save();
				}
				else
				{
					$tt = new AlertConfigDetail();
					$tt->where(['alert_config_id' => $aobj->id, 'item' => $item])->delete();
				}
			}
			catch(Exception $e)
			{
			}
		}
        return json(['status' => 0, 'message' => $items]);
    }
}
