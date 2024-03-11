<?php
namespace app\manage\controller;

use Redis;
use think\Request;
use think\Session;

use app\common\FrmController;
use app\model\Task;
use app\model\TaskTerminal;
use app\model\Terminal;

class TaskMgr extends FrmController
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
	
    public function getone(Request $request)
    {
		$uuid = $request->param('uuid');
		$model = new Task();
		$task = $model->getByUuid($uuid)->hidden(['id', 'enterprise_id']);
		$task->terminals2;
		/*
		foreach($terminals as $one)
		{
			print_r('======================');
			print_r($one->pivot->task_id);
			print_r('======================');
			print_r($one->id);
		}
		*/
		//$task->hidden(['terminals2.id', 'terminals2.enterprise_id', 'terminals2.type', 'terminals2.version']);
		//$task->hidden(['terminals2.pivot.status', 'terminals2.pivot.update_at']);
		foreach($task->terminals2 as $one)
		{
			$one->hidden(['id', 'enterprise_id', 'type', 'version', 'pivot.id', 'pivot.task_id', 'pivot.terminal_id']);
		}
		
		if($task->type==3 or $task->type==4)
		{
			$task->plugins = json_decode($task->content);
			if($task->plugins->type=='install')
				$task->plugins->type='插件安装';
			else if($task->plugins->type=='uninstall')
				$task->plugins->type='插件卸载';
			else if($task->plugins->type=='upgrade')
				$task->plugins->type='插件升级';
			else if($task->plugins->type=='lazy_exec')
				$task->plugins->type='延迟执行';
			else
				$task->plugins->type='未知';
			$task->hidden(['content', 'is_error']);
		}
        return json(['status' => 0, 'message' => '', 'result' => $task]);
    }
	
    public function getList()
    {
		$entid = Session::get('entid');
		$model = new Task();
		$result = $model->getByEnterprise($entid);
		
		foreach($result as $one)
		{
			$one->hidden(['id', 'enterprise_id']);
		}
        return json(['status' => 0, 'message' => '', 'result' => $result]);
    }
	
    public function getListPlg()
    {
		$entid = Session::get('entid');
		$model = new Task();
		$result = $model->getByEnterprisePlg($entid);
		
		foreach($result as $one)
		{
			$one->hidden(['id', 'enterprise_id']);
		}
        return json(['status' => 0, 'message' => '', 'result' => $result]);
    }
	
    public function add()
    {
		$data = file_get_contents('php://input');
        $json = json_decode($data);
        if(is_null($json))
        {
            return json(['status' => -1, 'message' => '参数不正确']);
        }
        
        if(property_exists($json, 'name')==false || property_exists($json, 'type')==false)
        {
            return json(['status' => -1, 'message' => '参数不正确']);
        }
        
		$content = '';
        if(property_exists($json, 'content'))
        {
            $content = $json->content;
        }
		
		$entid = Session::get('entid');
		$model = new Task();
		$model->enterprise_id = $entid;
		$model->uuid = uuid_create(1);
		$model->name = $json->name;
		$model->type = $json->type;
		$model->is_debug = $json->is_debug;
		$model->is_log = $json->is_log;
		$model->is_error = $json->is_error;
		$model->content = $content;
		$model->status = 0;
		$model->create_at = date("Y-m-d H:i:s", time());
		$model->update_at = $model->create_at;
        if($model->save())
        {
			return json(['status' => 0, 'message' => '保存成功']);
        }
        else
        {
			return json(['status' => -1, 'message' => '保存失败']);
        }
    }
	
	function edit(Request $request)
	{
		$data = file_get_contents('php://input');
        $json = json_decode($data);
        if(is_null($json))
        {
            return json(['status' => -1, 'message' => '参数不正确']);
        }
        
        if(property_exists($json, 'uuid')==false || property_exists($json, 'name')==false || property_exists($json, 'type')==false)
        {
            return json(['status' => -1, 'message' => '参数不正确']);
        }
        
		$content = '';
        if(property_exists($json, 'content'))
        {
            $content = $json->content;
        }
		
		$uuid = $request->param('uuid');
		$model = new Task();
		$task = $model->getByUuid($uuid);
		$task->name = $json->name;
		$task->type = $json->type;
		$task->is_debug = $json->is_debug;
		$task->is_log = $json->is_log;
		$task->is_error = $json->is_error;
		$task->content = $content;
		$task->update_at = date("Y-m-d H:i:s", time());
        if($task->save())
        {
			return json(['status' => 0, 'message' => '保存成功']);
        }
        else
        {
			return json(['status' => -1, 'message' => '保存失败']);
        }
	}
	
    public function delone(Request $request)
    {
		$entid = Session::get('entid');
		$objid = $request->param('objid');
		
		$model = new Task();
		$count = $model->where(['enterprise_id' => $entid, 'uuid' => $objid, 'status' => 0])->delete();
		if($count>=0)
		{
			return json(['status' => 0, 'message' => 'delete ' . $count]);
		}
		
        return json(['status' => -1, 'message' => '删除失败 ']);
    }
	
	function release(Request $request)
	{
		/*
		// parma 表示接收所有传过来的参数 不管是post请求还是get请求 parma都能接收到参数
　　　　//$data = $request->param();
　　　　// post表示只接收 post方式传出来的参数
　　　　//$data1= $request->post();
　　　　// get表示只接收get方式传出来的参数
　　　　//$data2= $request->get();
　　　　// 假如你只想拿到一个name值，这时我们可以在括号里面加上name即可。
　　　　//$data = $request->param('name');
*/
		$task_download_dir = '/opt/trc/tp5/task/';
		
		$uuid = $request->param('uuid');
		$model = new Task();
		$task = $model->getByUuid($uuid);
		
		
		$ret = file_put_contents($task_download_dir . $uuid, $task->content, FILE_APPEND | LOCK_EX);
		if($ret==false)
		{
			return json(['status' => -1, 'message' => '操作失败']);
		}
		
		$task->status = 1;
		$task->update_at = date("Y-m-d H:i:s", time());
        if($task->save())
        {
			$redis=new Redis();
			$redis->connect($this->redis_server, $this->redis_port, 5);
			$result = $redis->zAdd("frmstock:delay_message", ['NX'], time()+300, '{"dest":"trc:task:timeout", "body":"{\"task_id\":' . $task->id . '}"}');
			$redis->close();
			
			return json(['status' => 0, 'message' => '操作成功']);
        }
        else
        {
			return json(['status' => -1, 'message' => '操作失败']);
        }
	}
	
    public function addTerminal(Request $request)
    {
		$uuid = $request->param('uuid');
		$model = new Task();
		$task = $model->getByUuid($uuid);
		
		$tids = input('post.')['tids'];
		$model = new Terminal();
		foreach($tids as $tuuid)
		{
			$tobj = $model->getByUuid($tuuid);
			$tt = new TaskTerminal();
			$tt->task_id = $task->id;
			$tt->terminal_id = $tobj->id;
			$tt->status = 0;
			$tt->update_at = date("Y-m-d H:i:s", time());
			$tt->save();
		}
        return json(['status' => 0, 'message' => '']);
    }
	
    public function delTerminal(Request $request)
    {
		$uuid = $request->param('uuid');
		$tid = $request->param('tid');
		
		$model = new Terminal();
		$tobj = $model->getByUuid($tid);
		
		$model = new Task();
		$task = $model->getByUuid($uuid);
		if($task->status==0)	// todo need to optimize
		{
			$model = new TaskTerminal();
			$count = $model->where(['terminal_id' => $tobj->id, 'task_id' => $task->id])->delete();
			if($count>=0)
			{
				return json(['status' => 0, 'message' => 'delete ' . $count]);
			}
		}
		
        return json(['status' => -1, 'message' => '删除失败 ']);
	}
}
