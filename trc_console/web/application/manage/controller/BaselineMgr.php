<?php
namespace app\manage\controller;

use Redis;
use think\Request;
use think\Session;

use app\common\FrmController;
use app\model\Baseline;
use app\model\Terminal;

class BaselineMgr extends FrmController
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
	
    public function getList(Request $request)
    {
		$uuid = $request->param('objid');
		$model = new Terminal();
		$terminal = $model->getByUuid($uuid)->hidden(['id', 'enterprise_id', 'type', 'version']);
		
		$model = new Baseline();
		$result = $model->getByTerminal($terminal->id);
		foreach($result as $one)
		{
			$one->hidden(['id', 'terminal_id']);
		}
        return json(['status' => 0, 'message' => '', 'result' => $result, 'terminal' => $terminal]);
    }
	
    public function getTerminals()
    {
		$entid = Session::get('entid');
		if(count($_POST)==0)
		{
			$json = json_decode('{}');
		}
		else
		{
			$json = json_decode(json_encode($_POST));
		}
		
		$array = ["enterprise_id" => $entid, "type" => 2];
        if(property_exists($json, 'os'))
        {
			if($json->os!='')
				$array['host_os'] = ['like', $json->os."%"];
        }
		//$model = new Baseline();
		//$result = $model->where('item', 0)->with(['terminal' => function($query)use ($entid){$query->where(["enterprise_id" => $entid, "type" => 2]);}])->order('update_at', 'desc')->select();
        //$model = new Terminal();
		//$result = $model->baselines()->hasWhere(['item' => 0])->order('update_at', 'desc')->select();
		$result = Terminal::hasWhere("baselines", ['item' => 0])->field("Baseline.*,Terminal.*")->where($array)->order('Baseline.update_at', 'desc')->select();
		//$result = $model->baselines()->where(["terminal.enterprise_id" => $entid, "terminal.type" => 2, "baseline.item" => 0])->order('baseline.update_at', 'desc')->select();
		
		foreach($result as $one)
		{
			$one->hidden(['id', 'enterprise_id', 'type', 'version', 'terminal_id']);
		}
		return json(['status' => 0, 'message' => '', 'result' => $result, 'pftime' => time()-300]);
    }
	
    public function getListByItem(Request $request)
    {
		$entid = Session::get('entid');
		$item = $request->param('item');
		if($item<=0 or $item>16)
			return json(['status' => -1, 'message' => 'param error']);
		
		$model = new Terminal();
		$result = $model->where(["enterprise_id" => $entid, "type" => 2])->with(['baselines' => function($query)use ($item){$query->where('item',$item)->field('result,value,mark,update_at,terminal_id');}])->select();
		
		foreach($result as $one)
		{
			$one->hidden(['id', 'enterprise_id', 'type', 'version', 'baselines.terminal_id']);
		}
        return json(['status' => 0, 'message' => '', 'result' => $result]);
    }
	
    public function gethcom()
    {
        $result = [];
		$entid = Session::get('entid');
		
		$model = new Terminal();
		$array = ['type' => 2, 'enterprise_id' => $entid];
		$total = $model->where($array)->count();
		
		$redis=new Redis();
        $redis->connect($this->redis_server, $this->redis_port, 5);
		
		$result[1] = $redis->bitCount("bits:ent_" . $entid .  ":lbl_1");
		$result[2] = $redis->bitCount("bits:ent_" . $entid .  ":lbl_2");
		$result[3] = $redis->bitCount("bits:ent_" . $entid .  ":lbl_3");
		$result[4] = $redis->bitCount("bits:ent_" . $entid .  ":lbl_4");
		$result[16] = $redis->bitCount("bits:ent_" . $entid .  ":lbl_16");
		$result[5] = $redis->bitCount("bits:ent_" . $entid .  ":lbl_5");
		$result[6] = $redis->bitCount("bits:ent_" . $entid .  ":lbl_6");
		$result[7] = $redis->bitCount("bits:ent_" . $entid .  ":lbl_7");
		$result[8] = $redis->bitCount("bits:ent_" . $entid .  ":lbl_8");
		$result[9] = $redis->bitCount("bits:ent_" . $entid .  ":lbl_9");
		$result[14] = $redis->bitCount("bits:ent_" . $entid .  ":lbl_14");
		$result[15] = $redis->bitCount("bits:ent_" . $entid .  ":lbl_15");
		$result[10] = $redis->bitCount("bits:ent_" . $entid .  ":lbl_10");
		$result[11] = $redis->bitCount("bits:ent_" . $entid .  ":lbl_11");
		$result[12] = $redis->bitCount("bits:ent_" . $entid .  ":lbl_12");
		$result[13] = $redis->bitCount("bits:ent_" . $entid .  ":lbl_13");
        
        $redis->close();
		
        return json(['status' => 0, 'message' => '', 'result' => $result, 'total' => $total]);
    }
}
