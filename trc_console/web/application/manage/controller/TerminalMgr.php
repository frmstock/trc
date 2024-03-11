<?php
namespace app\manage\controller;

use Redis;
use think\Request;
use think\Session;

use app\common\FrmController;
use app\model\Task;
use app\model\Terminal;
use app\model\User;

class TerminalMgr extends FrmController
{
	private $redis_server;
	private $redis_port;
    public function _initialize()
    {
		parent::_initialize();
		
		try
		{
			$this->redis_server=config('redis_server');
			$this->redis_port=config('redis_port');
		}
		catch(Exception $e)
		{
			print_r($e);
		}
    }
	
    public function getList()
    {
		$entid = Session::get('entid');
		$model = new Terminal();
		$result = $model->getByEnterprise($entid);
		
		foreach($result as $one)
		{
			$one->hidden(['id', 'enterprise_id', 'type', 'version']);
		}
        return json(['status' => 0, 'message' => '', 'result' => $result, 'pftime' => time()-300]);
    }
	
    public function getList2(Request $request)
    {
		$uuid = $request->param('uuid');
		if(count($_POST)==0)
		{
			$json = json_decode('{}');
		}
		else
		{
			$json = json_decode(json_encode($_POST));
		}
		
		
		$model = new Task();
		$task = $model->getByUuid($uuid);
		
		$entid = Session::get('entid');
		$model = new Terminal();
		
		$array = ['enterprise_id' => $entid];
		if($task->type==1)
			$array['type'] = 1;
		else if($task->type==2)
			$array['type'] = 2;
		else if($task->type==3)
		{
			$array['type'] = 1;
			if($task->is_debug==1)
				$array['host_bits'] = '32';
			else if($task->is_debug==2)
				$array['host_bits'] = '64';
			//if($task->is_log==1)
			//	$array['host_bits'] = '64';
		}
		else if($task->type==4)
		{
			$array['type'] = 2;
			if($task->is_debug==1)
				$array['host_bits'] = '32';
			else if($task->is_debug==2)
				$array['host_bits'] = '64';
		}
		if(property_exists($json, 'os'))
        {
			if($json->os!='')
				$array['host_os'] = ['like', $json->os."%"];
        }
		
		$result = $model->where($array)->order('act_time', 'desc')->limit(0, 20)->select();
		foreach($result as $one)
		{
			$one->hidden(['id', 'enterprise_id', 'type', 'version']);
		}
        return json(['status' => 0, 'message' => '', 'result' => $result, 'pftime' => time()-300]);
    }
	
    public function getList3()
    {
		if(count($_POST)==0)
		{
			$json = json_decode('{}');
		}
		else
		{
			$json = json_decode(json_encode($_POST));
		}
		
		$entid = Session::get('entid');
		$model = new Terminal();
		$array = ['enterprise_id' => $entid];
		
        if(property_exists($json, 'os'))
        {
			if($json->os!='')
				$array['host_os'] = ['like', $json->os."%"];
        }
		
		$result = $model->where($array)->order('act_time', 'desc')->limit(0, 20)->select();
		foreach($result as $one)
		{
			$one->hidden(['id', 'enterprise_id', 'type', 'version']);
		}
        return json(['status' => 0, 'message' => '', 'result' => $result, 'pftime' => time()-300]);
    }
	
    public function getList4()
    {
		$entid = Session::get('entid');
		$model = new Terminal();
		$array = ['type' => 2, 'enterprise_id' => $entid];
		$result = $model->where($array)->order('act_time', 'desc')->limit(0, 20)->select();
        return json(['status' => 0, 'message' => '', 'result' => $result, 'pftime' => time()-300]);
    }
	
    public function delone(Request $request)
    {
		$entid = Session::get('entid');
		$objid = $request->param('objid');
		
		$model = new Terminal();
		$count = $model->where(['enterprise_id' => $entid, 'uuid' => $objid])->delete();
		if($count>0)
		{
			$redis=new Redis();
			$redis->connect($this->redis_server, $this->redis_port, 5);
			$result = $redis->zAdd("frmstock:delay_message", ['NX'], time()+300, '{"dest":"trc:ternimal:delete", "body":"{\"entid\":' . $entid . '}"}');
			$redis->close();
			
			return json(['status' => 0, 'message' => '']);
		}
		else if($count==0)
		{
			return json(['status' => 0, 'message' => 'delete 0']);
		}
		
        return json(['status' => -1, 'message' => '删除失败 ']);
    }
}
