<?php
namespace app\manage\controller;

use Redis;
use think\Request;
use think\Session;

use app\common\FrmController;

class RedisMgr extends FrmController
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
	
    public function getPlugins(Request $request)
    {
        $result = [];
		$entid = Session::get('entid');
		$objid = $request->param('objid');
		
		$redis=new Redis();
        $redis->connect($this->redis_server, $this->redis_port, 5);
		$result = $redis->hGetAll("plugins:update:" . $objid);
        $redis->close();
		
        return json(['status' => 0, 'message' => '', 'result' => $result]);
    }
	
    public function getPluginsCntrpm(Request $request)
    {
        $result = [];
		$objid = $request->param('objid');
		
		$redis=new Redis();
        $redis->connect($this->redis_server, $this->redis_port, 5);
		$result_tmp = $redis->zRange("plugins:cntrpm:" . $objid, 0, -1);
        $redis->close();
		
		foreach($result_tmp as $key => $item)
		{
			$tmp = json_decode($item);
			unset($tmp->entid);
			unset($tmp->objid);
			$result[$key] = $tmp;
		}
        return json(['status' => 0, 'message' => '', 'result' => $result]);
    }
	
    public function getPluginsCntrec(Request $request)
    {
        $result = [];
		$objid = $request->param('objid');
		
		$redis=new Redis();
        $redis->connect($this->redis_server, $this->redis_port, 5);
		$result_tmp = $redis->zRange("plugins:cntrec:" . $objid, 0, -1);
        $redis->close();
		
		foreach($result_tmp as $key => $item)
		{
			$tmp = json_decode($item);
			unset($tmp->action);
			unset($tmp->entid);
			unset($tmp->objid);
			unset($tmp->local_ip_int);
			unset($tmp->remote_ip_int);
			unset($tmp->status);
			unset($tmp->inode);
			$result[$key] = $tmp;
		}
        return json(['status' => 0, 'message' => '', 'result' => $result]);
    }
}
