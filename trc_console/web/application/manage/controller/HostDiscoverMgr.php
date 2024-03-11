<?php
namespace app\manage\controller;

use Redis;
use think\Request;
use think\Session;

use app\common\FrmController;
use app\model\ArpHosts;
use app\model\Terminal;

class HostDiscoverMgr extends FrmController
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
	
    public function getNewHosts()
    {
		$entid = Session::get('entid');
		$model = new ArpHosts();
		$hosts = $model->getNewHosts($entid);
		foreach($hosts as $one)
		{
			$one->hidden(['enterprise_id', 'terminal_id']);
			$one->activity = $one->arplogs()->count();
		}
        return json(['status' => 0, 'message' => '', 'result' => $hosts]);
    }
}
