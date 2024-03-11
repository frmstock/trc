<?php
namespace app\manage\controller;

use Redis;
use think\Request;
use think\Session;

use app\common\FrmController;
use app\model\ExportTask;

class ExportTaskMgr extends FrmController
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
		$model = new ExportTask();
		$result = $model->getByEnterprise($entid);
		foreach($result as $one)
		{
			$one->isfinish = $one->status==2;
			$one->status = config('ExportTaskConfig.Status')[$one->status];
			$one->type = config('ExportTaskConfig.Type')[$one->type];
			$one->visible(['type', 'status', 'isfinish', 'objid', 'uuid', 'file_name', 'create_at', 'exec_at', 'finish_at']);
		}
        return json(['status' => 0, 'message' => '', 'result' => $result]);
    }
	
	public function add(Request $request)
    {
		$entid = Session::get('entid');
		$objid = $request->param('objid');
		$type = $request->param('type');
		
		if(array_key_exists($type, config("ExportTaskConfig.Type"))==false)
		{
			return json(['status' => -1, 'message' => 'type error.']);
		}
		
		$model = new ExportTask();
		$model->enterprise_id = $entid;
		$model->objid = $objid;
		$model->uuid = uuid_create(1);
		$model->type = $type;
		$model->create_at = date("Y-m-d H:i:s", time());
		if($model->save())
        {
			$redis=new Redis();
            $redis->connect($this->redis_server, $this->redis_port, 5);
            $redis->lpush("export_data", json_encode(array('objid' => $objid, 'uuid' =>$model->uuid, 'type' => config("ExportTaskConfig.Tool")[$type]), JSON_UNESCAPED_UNICODE));
			$redis->close();
			return json(['status' => 0, 'message' => '保存成功']);
        }
        else
        {
			return json(['status' => -1, 'message' => '保存失败']);
        }
    }
}
