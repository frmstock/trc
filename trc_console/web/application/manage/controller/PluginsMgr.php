<?php
namespace app\manage\controller;

use Redis;
use think\Request;
use think\Session;

use app\common\FrmController;
use app\model\Plugins;
use app\model\PluginsFiles;
use app\model\Task;
use app\model\TaskTerminal;
use app\model\Terminal;

class PluginsMgr extends FrmController
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
		$model = new Plugins();
		$result = $model->getAll();
		
		foreach($result as $one)
		{
			$one->hidden(['id']);
		}
        return json(['status' => 0, 'message' => '', 'result' => $result]);
    }
	
    public function getFiles(Request $request)
    {
		$version = $request->param('objid');
		
		$model = new Plugins();
		$plugins = $model->getByVersion($version);
		
		$model = new PluginsFiles();
		$result = $model->getByPlugins($version);
		foreach($result as $one)
		{
			$file_name = $plugins->code;
			if($plugins->os_type==1)
				$file_name=$plugins->code . '.exe';
				
			if($one->file_type==1)
			{
				$one->file_name = $file_name;
				$one->file_path = 'plug-ins/' . $file_name;
			}
			else if($one->file_type==2)
			{
				$one->file_name = $plugins->code . '.conf';
				$one->file_path = 'cron/' . $plugins->code . '.conf';
			}
			else if($one->file_type==3)
			{
				$one->file_name = $plugins->code . '.conf';
				$one->file_path = 'conf/' . $plugins->code . '.conf';
			}
			else
			{
				$one->file_name = $plugins->code;
				$one->file_path = $plugins->code;
			}
			$one->hidden(['id', 'file_type']);
		}
        return json(['status' => 0, 'message' => '', 'result' => $result, 'name' => $plugins->code]);
    }
	
    public function lazy_exec(Request $request)
    {
		$task_save_dir = '/opt/task/';
		$task_download_dir = '/opt/tp5/application/www/task/';
		$entid = Session::get('entid');
		$tuuid = $request->param('objid');
		$name = $request->param('name');
		$version = $request->param('version');
		
		$task_obj = new Task();
		$model = new Terminal();
		$terminal_obj = $model->getByUuid($tuuid);
		if($terminal_obj->type==1)
		{
			$task_obj->type = 3;
			$task_obj->content = '{"type":"lazy_exec", "ostype":"win", "name":"' . $name . '", "md5":"' . $version . '", "files":[]}';
		}
		else
		{
			$task_obj->type = 4;
			$task_obj->content = '{"type":"lazy_exec", "ostype":"linux", "name":"' . $name . '", "md5":"' . $version . '", "files":[]}';
		}
		
		$task_obj->enterprise_id = $entid;
		$task_obj->uuid = uuid_create(1);
		$task_obj->name = 'lazy_exec plugins ' . $name;
		$task_obj->is_debug = 0;
		$task_obj->is_log = 0;
		$task_obj->is_error = 0;
		$task_obj->status = 1;
		$task_obj->create_at = date("Y-m-d H:i:s", time());
		$task_obj->update_at = $task_obj->create_at;
		
		$ret = file_put_contents($task_download_dir . $task_obj->uuid, $task_obj->content, FILE_APPEND | LOCK_EX);
		if($ret==false)
		{
			return json(['status' => -1, 'message' => '保存失败']);
		}
		
        if($task_obj->save())
        {
			$tt_obj = new TaskTerminal();
			$tt_obj->task_id = $task_obj->id;
			$tt_obj->terminal_id = $terminal_obj->id;
			$tt_obj->status = 0;
			$tt_obj->update_at = $task_obj->create_at;
			if($tt_obj->save())
			{
				$redis=new Redis();
				$redis->connect($this->redis_server, $this->redis_port, 5);
				$result = $redis->zAdd("frmstock:delay_message", ['NX'], time()+300, '{"dest":"trc:task:timeout", "body":"{\"task_id\":' . $task_obj->id . '}"}');
				$redis->close();
				
				return json(['status' => 0, 'message' => '操作成功']);
			}
        }
		
        return json(['status' => -1, 'message' => '保存失败']);
    }
	
    public function uninstall(Request $request)
    {
		$task_save_dir = '/opt/task/';
		$task_download_dir = '/opt/tp5/application/www/task/';
		$entid = Session::get('entid');
		$tuuid = $request->param('objid');
		$name = $request->param('name');
		$version = $request->param('version');
		
		$task_obj = new Task();
		$model = new Terminal();
		$terminal_obj = $model->getByUuid($tuuid);
		if($terminal_obj->type==1)
		{
			$task_obj->type = 3;
			$task_obj->content = '{"type":"uninstall", "ostype":"win", "name":"' . $name . '", "md5":"", "files":[]}';
		}
		else
		{
			$task_obj->type = 4;
			$task_obj->content = '{"type":"uninstall", "ostype":"linux", "name":"' . $name . '", "md5":"' . $version . '", "files":[]}';
		}
		
		$task_obj->enterprise_id = $entid;
		$task_obj->uuid = uuid_create(1);
		$task_obj->name = 'uninstall plugins ' . $name;
		$task_obj->is_debug = 0;
		$task_obj->is_log = 0;
		$task_obj->is_error = 0;
		$task_obj->status = 1;
		$task_obj->create_at = date("Y-m-d H:i:s", time());
		$task_obj->update_at = $task_obj->create_at;
		
		$ret = file_put_contents($task_download_dir . $task_obj->uuid, $task_obj->content, FILE_APPEND | LOCK_EX);
		if($ret==false)
		{
			return json(['status' => -1, 'message' => '保存失败']);
		}
		
        if($task_obj->save())
        {
			$tt_obj = new TaskTerminal();
			$tt_obj->task_id = $task_obj->id;
			$tt_obj->terminal_id = $terminal_obj->id;
			$tt_obj->status = 0;
			$tt_obj->update_at = $task_obj->create_at;
			if($tt_obj->save())
			{
				$redis=new Redis();
				$redis->connect($this->redis_server, $this->redis_port, 5);
				$result = $redis->zAdd("frmstock:delay_message", ['NX'], time()+300, '{"dest":"trc:task:timeout", "body":"{\"task_id\":' . $task_obj->id . '}"}');
				$redis->close();
				
				return json(['status' => 0, 'message' => '操作成功']);
			}
        }
		
        return json(['status' => -1, 'message' => '保存失败']);
    }
	
	public function taskInstall(Request $request)
    {
		$entid = Session::get('entid');
		$version = $request->param('objid');
		
		$model = new Plugins();
		$plugins = $model->getByVersion($version);
		if($plugins==null)
		{
			return json(['status' => -1, 'message' => '操作失败']);
		}
		
		$plg_files = '';
		$model = new PluginsFiles();
		$file_list = $model->getByPlugins($version);
		foreach($file_list as $one)
		{
			$file_url = 'update/plug-ins/';
			$file_dir = '';
			$file_md5 = $one->file_md5;
			$file_name = $plugins->code;
			if($plugins->os_type==1)
				$file_name=$plugins->code . '.exe';
				
			if($one->file_type==1)
			{
				$file_dir = 'plug-ins';
				$file_url = 'update/plug-ins/' . $plugins->version . '/plug-ins/' . $file_name;
			}
			else if($one->file_type==2)
			{
				$file_dir = 'cron';
				$file_url = 'update/plug-ins/' . $plugins->version . '/cron/' . $plugins->code . '.conf';
			}
			else if($one->file_type==3)
			{
				$file_dir = 'conf';
				$file_url = 'update/plug-ins/' . $plugins->version . '/conf/' . $plugins->code . '.conf';
			}
			else
			{
				return json(['status' => -1, 'message' => '操作失败']);
			}
			
			if($plg_files=='')
				$plg_files = '{"url":"' . $file_url . '", "dir":"' . $file_dir . '", "md5":"' . $file_md5 . '"}';
			else
				$plg_files = $plg_files . ',{"url":"' . $file_url . '", "dir":"' . $file_dir . '", "md5":"' . $file_md5 . '"}';
		}
		
		$task_obj = new Task();
		if($plugins->os_type==1)
		{
			$task_obj->type = 3;
			$task_obj->content = '{"type":"install", "ostype":"win", "name":"' . $plugins->code . '", "md5":"' . $plugins->version . '", "files":[' . $plg_files . ']}';
		}
		else
		{
			$task_obj->type = 4;
			$task_obj->content = '{"type":"install", "ostype":"linux", "name":"' . $plugins->code . '", "md5":"' . $plugins->version . '", "files":[' . $plg_files . ']}';
		}
		
		if($plugins->os_list=='')
			$task_obj->is_log = 0;
		else
			$task_obj->is_log = 1;
		
		$task_obj->enterprise_id = $entid;
		$task_obj->uuid = uuid_create(1);
		$task_obj->name = 'install plugins ' . $plugins->code;
		$task_obj->is_debug = $plugins->os_bits;
		$task_obj->is_error = 0;
		$task_obj->status = 0;
		$task_obj->create_at = date("Y-m-d H:i:s", time());
		$task_obj->update_at = $task_obj->create_at;
		if($task_obj->save())
		{
			return json(['status' => 0, 'message' => '保存成功', 'objid' => $task_obj->uuid]);
		}
		
		return json(['status' => -1, 'message' => '操作失败']);
	}
	
	public function taskUninstall(Request $request)
    {
		$entid = Session::get('entid');
		$version = $request->param('objid');
		
		$model = new Plugins();
		$plugins = $model->getByVersion($version);
		if($plugins==null)
		{
			return json(['status' => -1, 'message' => '操作失败']);
		}
		
		$task_obj = new Task();
		if($plugins->os_type==1)
		{
			$task_obj->type = 3;
			$task_obj->content = '{"type":"uninstall", "ostype":"win", "name":"' . $plugins->code . '", "md5":"' . $plugins->version . '", "files":[]}';
		}
		else
		{
			$task_obj->type = 4;
			$task_obj->content = '{"type":"uninstall", "ostype":"linux", "name":"' . $plugins->code . '", "md5":"' . $plugins->version . '", "files":[]}';
		}
		
		if($plugins->os_list=='')
			$task_obj->is_log = 0;
		else
			$task_obj->is_log = 1;
		
		$task_obj->enterprise_id = $entid;
		$task_obj->uuid = uuid_create(1);
		$task_obj->name = 'uninstall plugins ' . $plugins->code;
		$task_obj->is_debug = $plugins->os_bits;
		$task_obj->is_error = 0;
		$task_obj->status = 0;
		$task_obj->create_at = date("Y-m-d H:i:s", time());
		$task_obj->update_at = $task_obj->create_at;
		if($task_obj->save())
		{
			return json(['status' => 0, 'message' => '保存成功', 'objid' => $task_obj->uuid]);
		}
		
		return json(['status' => -1, 'message' => '操作失败']);
	}
}
