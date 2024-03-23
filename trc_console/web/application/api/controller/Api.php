<?php
namespace app\api\controller;

use Redis;
use think\Db;

use app\model\Enterprise;
use app\model\Task;
use app\model\TaskTerminal;
use app\model\Terminal;

class Api
{
	private $redis_server;
	private $redis_port;
	public function __construct()
    {
        date_default_timezone_set("PRC");
        $this->redis_server=config('redis_server');
        $this->redis_port=config('redis_port');
    }
	
    public function register()
    {
		$cur_time = time();
        $data = file_get_contents('php://input');
        $json = json_decode($data);
        if(is_null($json))
        {
            print_r('{"code":"error", "message":"param error."}');
            return;
        }
        
        if(!property_exists($json, 'entid'))
        {
            print_r('{"code":"error", "message":"param error."}');
            return;
        }
        
        $model = new Enterprise();
        $ent = $model->getByUuid($json->entid);
        if(!(array)$ent)
        {
            print_r('{"code":"error", "message":"entid does not exist."}');
            return;
        }
        
        if(!property_exists($json, 'uuid'))
        {
            $json->uuid = uuid_create(1);
        }
        
        $model = new Terminal();
        $terminal = $model->getByUuid($json->uuid);
        if((array)$terminal)
        {
            print_r('{"code":"error", "message":"uuid already exists."}');
            return;
        }
        
		$model->enterprise_id = $ent->id;
        $model->uuid = $json->uuid;
        if(property_exists($json, 'type'))
            $model->type = $json->type;
        else
            $model->type = 1;
        $model->reg_time = date("Y-m-d H:i:s", $cur_time);
        $model->act_time = $model->reg_time;
		
        if(property_exists($json, 'hostinfo'))
        {
			if(property_exists($json->hostinfo, 'ip'))
				$model->host_ip = $json->hostinfo->ip;
			
			if(property_exists($json->hostinfo, 'name'))
				$model->host_name = $json->hostinfo->name;
			
			if(property_exists($json->hostinfo, 'os'))
				$model->host_os = $json->hostinfo->os;
			
			if(property_exists($json->hostinfo, 'version'))
				$model->host_version = $json->hostinfo->version;
			
			if(property_exists($json->hostinfo, 'bits'))
				$model->host_bits = $json->hostinfo->bits;
        }
		
        if($model->save()==false)
        {
            print_r('{"code":"error", "message":"register failed."}');
        }
        else
        {
			$redis=new Redis();
			$redis->connect($this->redis_server, $this->redis_port, 5);
			$redis->lpush("terminal_register", '{"uuid":"' . $model->uuid . '", "entid":' . $ent->id . ', "objid":' . $model->id . ', "type":' . $model->type . "}");
			
			$alert = array('terminal' => $model, 'category' => 1, 'type' => 'register', 'item' => 4, 'pftime' => $cur_time, 'eptime' => 0, 'epnano' => 0, 'content' => '终端注册 注册时间: ' . $model->reg_time);
			$redis->lpush("alerts", json_encode($alert, JSON_UNESCAPED_UNICODE));
			$redis->close();
			
            print_r('{"code":"ok", "objid":"' . $json->uuid . '", "message":""}');
        }
    }
    
     public function heartbeat()
    {
        $data = file_get_contents('php://input');
        $json = json_decode($data);
        if(is_null($json))
        {
            return json(['code' => -1, 'message' => 'Non JSON format.']);
        }
        
        if(property_exists($json, 'type') && property_exists($json, 'objid') && property_exists($json, 'seq') && property_exists($json, 'ver') && property_exists($json, 'host_time') && property_exists($json, 'uptime'))
        {
            if($json->type != "heartbeat")
            {
                return json(['code' => -3, 'message' => 'param(type) error.']);
            }
            
            $model = new Terminal();
            $terminal = $model->getByUuid($json->objid);
            if((array)$terminal)
            {
				$pftime = time();
                $incr = rand(10,100);
                $seq = $json->seq;
				$ms_code = "xxxxxxxxxxxxxxxxxxxx";
                
                $redis=new Redis();
                $redis->connect($this->redis_server, $this->redis_port, 5);
                $next_seq = $redis->incr($json->objid, $incr);
                $redis->zadd("online", $pftime, $json->objid);
				#
				$token = $this->getToken($json->objid, $json->host_time);
				if($token->code!=0)
				{
					return json(['code' => -6, 'message' => $token->message]);
				}
				
				if(property_exists($json, 'plug_ins'))
				{
					if(property_exists($json->plug_ins, 'checkbaseline'))
					{
						$json->plug_ins->checkbaseline->entid = $terminal->enterprise_id;
						$json->plug_ins->checkbaseline->objuuid = $terminal->uuid;
						$json->plug_ins->checkbaseline->objid = $terminal->id;
						$json->plug_ins->checkbaseline->eptime = $json->host_time;
						$json->plug_ins->checkbaseline->pftime = $pftime;
						$redis->lpush("baseline_linux", json_encode($json->plug_ins->checkbaseline, JSON_UNESCAPED_UNICODE));
						$redis->ltrim("baseline_linux", 0, 299);
						
						$redis->hSet("plugins:update:" . $terminal->uuid, "checkbaseline", "{\"name\":\"checkbaseline\", \"version\":\"" . $json->plug_ins->checkbaseline->self . "\", \"conf\":\"\"}");
						unset($json->plug_ins->checkbaseline);
					}
					if(property_exists($json->plug_ins, 'lpm'))
					{
						$redis->hSet("plugins:update:" . $terminal->uuid, "lpm", "{\"name\":\"lpm\", \"version\":\"" . $json->plug_ins->lpm->self . "\", \"conf\":\"\"}");
						
						if($json->plug_ins->lpm->mode==1)
						{
							$redis->lpush("lpm", "{\"entid\":\"" . $terminal->enterprise_id . "\", \"objuuid\":\"" . $terminal->uuid 
							                      . "\", \"objid\":\"" . $terminal->id . "\", \"eptime\":\"" . $json->host_time 
												  . "\", \"pftime\":\"" . $pftime 
												  . "\", \"action\":\"clear\", \"name\":\"\", \"version\":\"\"}");
						}
						if($json->plug_ins->lpm->mode==3)
						{
							$redis->lpush("lpm", "{\"entid\":\"" . $terminal->enterprise_id . "\", \"objuuid\":\"" . $terminal->uuid 
							                      . "\", \"objid\":\"" . $terminal->id . "\", \"eptime\":\"" . $json->host_time 
												  . "\", \"pftime\":\"" . $pftime 
												  . "\", \"action\":\"vulscan\", \"kernel\":\"" . $json->plug_ins->lpm->kernel_release . "\"}");
						}
						else
						{
							$redis->lpush("lpm", "{\"entid\":\"" . $terminal->enterprise_id . "\", \"objuuid\":\"" . $terminal->uuid 
							                      . "\", \"objid\":\"" . $terminal->id . "\", \"eptime\":\"" . $json->host_time 
												  . "\", \"pftime\":\"" . $pftime 
												  . "\", \"action\":\"detection\", \"name\":\"\", \"version\":\"\"}");
						}
						
						if(property_exists($json->plug_ins->lpm, 'lpmlist'))
						{
							foreach($json->plug_ins->lpm->lpmlist as $lpmitem)
							{
								$lpmitem->entid = $terminal->enterprise_id;
								$lpmitem->objuuid = $terminal->uuid;
								$lpmitem->objid = $terminal->id;
								$lpmitem->eptime = $json->host_time;
								$lpmitem->pftime = $pftime;
								
								$redis->lpush("lpm", json_encode($lpmitem, JSON_UNESCAPED_UNICODE));
								$redis->ltrim("lpm", 0, 1999);
							}
						}
						unset($json->plug_ins->lpm);
					}
					if(property_exists($json->plug_ins, 'sysperf'))
					{
						$redis->hSet("plugins:update:" . $terminal->uuid, "sysperf", "{\"name\":\"sysperf\", \"version\":\"" . $json->plug_ins->sysperf->self . "\", \"conf\":\"" . $json->plug_ins->sysperf->conf . "\"}");
						
						unset($json->plug_ins->sysperf->self);
						unset($json->plug_ins->sysperf->conf);
						$json->plug_ins->sysperf->entid = $terminal->enterprise_id;
						$json->plug_ins->sysperf->objuuid = $terminal->uuid;
						$json->plug_ins->sysperf->objid = $terminal->id;
						$json->plug_ins->sysperf->eptime = $json->host_time;
						$json->plug_ins->sysperf->pftime = $pftime;
						$redis->lpush("plugins:sysperf", json_encode($json->plug_ins->sysperf, JSON_UNESCAPED_UNICODE));
						$redis->ltrim("plugins:sysperf", 0, 99);
						unset($json->plug_ins->sysperf);
					}
					if(property_exists($json->plug_ins, 'systat'))
					{
						$redis->hSet("plugins:update:" . $terminal->uuid, "systat", "{\"name\":\"systat\", \"version\":\"" . $json->plug_ins->systat->self . "\", \"conf\":\"\"}");

						unset($json->plug_ins->systat->self);
						$json->plug_ins->systat->entid = $terminal->enterprise_id;
						$json->plug_ins->systat->objuuid = $terminal->uuid;
						$json->plug_ins->systat->objid = $terminal->id;
						$json->plug_ins->systat->eptime = $json->host_time;
						$json->plug_ins->systat->pftime = $pftime;
						$redis->lpush("plugins:systat", json_encode($json->plug_ins->systat, JSON_UNESCAPED_UNICODE));
						$redis->ltrim("plugins:systat", 0, 99);
						unset($json->plug_ins->systat);
					}
					if(property_exists($json->plug_ins, 'procperf'))
					{
						if(property_exists($json->plug_ins->procperf, 'procs'))
						{
							foreach($json->plug_ins->procperf->procs as $proc)
							{
								$proc->entid = $terminal->enterprise_id;
								$proc->objuuid = $terminal->uuid;
								$proc->objid = $terminal->id;
								$proc->eptime = $json->host_time;
								$proc->pftime = $pftime;
							
								$redis->lpush("plugins:procperf", json_encode($proc, JSON_UNESCAPED_UNICODE));
								$redis->ltrim("plugins:procperf", 0, 99);
							}
						}
						
						if(property_exists($json->plug_ins->procperf, 'alerts'))
						{
							$number=0;
							foreach($json->plug_ins->procperf->alerts as $alert)
							{
								$number=$number+1;
								if(array_key_exists($alert->type, config('AlertConfigItem.procmon')))
								{
									$alert->terminal = $terminal;
									$alert->eptime = $json->host_time;
									$alert->pftime = $pftime;
									$alert->epnano = $number;
									$alert->category = 2;
									$alert->item = config('AlertConfigItem.procmon')[$alert->type];
									
									$redis->lpush("alerts", json_encode($alert, JSON_UNESCAPED_UNICODE));
									$redis->ltrim("alerts", 0, 99);
								}
							}
						}
						
						$redis->hSet("plugins:update:" . $terminal->uuid, "procperf", "{\"name\":\"procperf\", \"version\":\"" . $json->plug_ins->procperf->self . "\", \"conf\":\"" . $json->plug_ins->procperf->conf . "\"}");
						unset($json->plug_ins->procperf);
					}
					if(property_exists($json->plug_ins, 'tcpmon'))
					{
						if(property_exists($json->plug_ins->tcpmon, 'tcplist'))
						{
							$number=0;
							foreach($json->plug_ins->tcpmon->tcplist as $tcpitem)
							{
								$tcpitem->entid = $terminal->enterprise_id;
								$tcpitem->objuuid = $terminal->uuid;
								$tcpitem->objid = $terminal->id;
								$tcpitem->eptime = $json->host_time;
								$tcpitem->pftime = $pftime;
								$redis->lpush("plugins:tcpmon", json_encode($tcpitem, JSON_UNESCAPED_UNICODE));
								$redis->ltrim("plugins:tcpmon", 0, 99);
								
								$number=$number+1;
								$alertitem=config('AlertConfigItem.tcpmon')[$tcpitem->action];
								$content=config('AlertConfigItem.name')[$alertitem] . ' ' . $tcpitem->local_ip . ':' . $tcpitem->local_port . ' <-> ' . $tcpitem->remote_ip . ':' . $tcpitem->remote_port;
								$alert = array('terminal' => $terminal, 'category' => 3, 'type' => $tcpitem->action, 'item' => $alertitem, 'pftime' => time(), 'eptime' => $json->host_time, 'epnano' => $number, 'content' => $content);
								$redis->lpush("alerts", json_encode($alert, JSON_UNESCAPED_UNICODE));
								$redis->ltrim("alerts", 0, 99);
							}
						}
						
						$redis->hSet("plugins:update:" . $terminal->uuid, "tcpmon", "{\"name\":\"tcpmon\", \"version\":\"" . $json->plug_ins->tcpmon->self . "\", \"conf\":\"" . $json->plug_ins->tcpmon->conf . "\"}");
						unset($json->plug_ins->tcpmon);
					}
					if(property_exists($json->plug_ins, 'privesccheck'))
					{
						if(property_exists($json->plug_ins->privesccheck, 'procs'))
						{
							foreach($json->plug_ins->privesccheck->procs as $proc)
							{
								$proc->entid = $terminal->enterprise_id;
								$proc->objuuid = $terminal->uuid;
								$proc->objid = $terminal->id;
								$proc->eptime = $json->host_time;
								$proc->pftime = $pftime;
							
								$redis->lpush("plugins:privesccheck", json_encode($proc, JSON_UNESCAPED_UNICODE));
								$redis->ltrim("plugins:privesccheck", 0, 99);
							}
						}
						
						$redis->hSet("plugins:update:" . $terminal->uuid, "privesccheck", "{\"name\":\"privesccheck\", \"version\":\"" . $json->plug_ins->privesccheck->self . "\", \"conf\":\"\"}");
						unset($json->plug_ins->privesccheck);
					}
					if(property_exists($json->plug_ins, 'cntrpm'))
					{
						if(property_exists($json->plug_ins->cntrpm, 'pslist'))
						{
							$redis->del("plugins:cntrpm:" . $terminal->uuid);
							foreach($json->plug_ins->cntrpm->pslist as $psitem)
							{
								$psitem->entid = $terminal->enterprise_id;
								$psitem->objuuid = $terminal->uuid;
								$psitem->objid = $terminal->id;
								$psitem->eptime = $json->host_time;
								$psitem->pftime = $pftime;
								$redis->lpush("plugins:cntrpm:pslist", json_encode($psitem, JSON_UNESCAPED_UNICODE));
								$redis->ltrim("plugins:cntrpm:pslist", 0, 99);
								
								//if(strpos($terminal->host_os, "Windows ") === 0)
								//{
								//	$psitem->os_type = 'windows';
								//	$psitem->file_type = 'exe';
								//}
								//else
								//{
								//	$psitem->os_type = $terminal->host_os;
								//	$psitem->file_type = 'elf';
								//}
								//$psitem->os_bit = $terminal->host_bits;
								//$psitem->file_from = 1;
								//$redis->lpush("file_library:todo", json_encode($psitem, JSON_UNESCAPED_UNICODE));
							}
						}
						if(property_exists($json->plug_ins->cntrpm, 'loglist'))
						{
							#$number=0;
							foreach($json->plug_ins->cntrpm->loglist as $logitem)
							{
								$logitem->entid = $terminal->enterprise_id;
								$logitem->objuuid = $terminal->uuid;
								$logitem->objid = $terminal->id;
								$logitem->eptime = $json->host_time;
								$logitem->pftime = $pftime;
								$redis->lpush("plugins:cntrpm:loglist", json_encode($logitem, JSON_UNESCAPED_UNICODE));
								$redis->ltrim("plugins:cntrpm:loglist", 0, 99);
								//if($logitem->action != 'start')
								//	continue;
								//
								//if(strpos($terminal->host_os, "Windows ") === 0)
								//{
								//	$logitem->os_type = 'windows';
								//	$logitem->file_type = 'exe';
								//}
								//else
								//{
								//	$logitem->os_type = $terminal->host_os;
								//	$logitem->file_type = 'elf';
								//}
								//$logitem->os_bit = $terminal->host_bits;
								//$logitem->file_from = 1;
								//$redis->lpush("file_library:todo", json_encode($logitem, JSON_UNESCAPED_UNICODE));
							}
						}
						
						$redis->hSet("plugins:update:" . $terminal->uuid, "cntrpm", "{\"name\":\"cntrpm\", \"version\":\"" . $json->plug_ins->cntrpm->self . "\", \"conf\":\"" . $json->plug_ins->cntrpm->conf . "\"}");
						unset($json->plug_ins->cntrpm);
					}
					if(property_exists($json->plug_ins, 'cntrec'))
					{
						if(property_exists($json->plug_ins->cntrec, 'eclist'))
						{
							$redis->del("plugins:cntrec:" . $terminal->uuid);
							foreach($json->plug_ins->cntrec->eclist as $psitem)
							{
								$psitem->entid = $terminal->enterprise_id;
								$psitem->objuuid = $terminal->uuid;
								$psitem->objid = $terminal->id;
								$psitem->eptime = $json->host_time;
								$psitem->pftime = $pftime;
								$redis->lpush("plugins:cntrec:eclist", json_encode($psitem, JSON_UNESCAPED_UNICODE));
								$redis->ltrim("plugins:cntrec:eclist", 0, 99);
							}
						}
						if(property_exists($json->plug_ins->cntrec, 'loglist'))
						{
							#$number=0;
							foreach($json->plug_ins->cntrec->loglist as $logitem)
							{
								$logitem->entid = $terminal->enterprise_id;
								$logitem->objuuid = $terminal->uuid;
								$logitem->objid = $terminal->id;
								$logitem->eptime = $json->host_time;
								$logitem->pftime = $pftime;
								$redis->lpush("plugins:cntrec:loglist", json_encode($logitem, JSON_UNESCAPED_UNICODE));
								$redis->ltrim("plugins:cntrec:loglist", 0, 99);
							}
						}
						
						$redis->hSet("plugins:update:" . $terminal->uuid, "cntrec", "{\"name\":\"cntrec\", \"version\":\"" . $json->plug_ins->cntrec->self . "\", \"conf\":\"" . $json->plug_ins->cntrec->conf . "\"}");
						unset($json->plug_ins->cntrec);
					}
					if(property_exists($json->plug_ins, 'hostdiscover'))
					{
						$redis->hSet("plugins:update:" . $terminal->uuid, "hostdiscover", "{\"name\":\"hostdiscover\", \"version\":\"" . $json->plug_ins->hostdiscover->self . "\"}");
						
						if(property_exists($json->plug_ins->hostdiscover, 'ip_list'))
						{
							foreach($json->plug_ins->hostdiscover->ip_list as $ipitem)
							{
								$ipitem->entid = $terminal->enterprise_id;
								$ipitem->objuuid = $terminal->uuid;
								$ipitem->objid = $terminal->id;
								$ipitem->eptime = $json->host_time;
								$ipitem->pftime = $pftime;
								$redis->lpush("plugins:hostdiscover:ip_list", json_encode($ipitem, JSON_UNESCAPED_UNICODE));
								$redis->ltrim("plugins:hostdiscover:ip_list", 0, 99);
							}
						}
						if(property_exists($json->plug_ins->hostdiscover, 'arp_list'))
						{
							foreach($json->plug_ins->hostdiscover->arp_list as $logitem)
							{
								$logitem->entid = $terminal->enterprise_id;
								$logitem->objuuid = $terminal->uuid;
								$logitem->objid = $terminal->id;
								$logitem->eptime = $json->host_time;
								$logitem->pftime = $pftime;
								$redis->lpush("plugins:hostdiscover:arp_list", json_encode($logitem, JSON_UNESCAPED_UNICODE));
								$redis->ltrim("plugins:hostdiscover:arp_list", 0, 99);
							}
						}
						
						unset($json->plug_ins->hostdiscover);
					}
					
					foreach ($json->plug_ins as $key => $value)
					{
						$redis->hSet("plugins:update:" . $terminal->uuid, $key, "{\"name\":\"" . $key . "\", \"version\":\"\", \"conf\":\"\"}");
						
						$json->plug_ins->$key->entid = $terminal->enterprise_id;
						$json->plug_ins->$key->objuuid = $terminal->uuid;
						$json->plug_ins->$key->objid = $terminal->id;
						$json->plug_ins->$key->eptime = $json->host_time;
						$json->plug_ins->$key->pftime = $pftime;
						$redis->lpush("plugins:" . $key, json_encode($json->plug_ins->$key, JSON_UNESCAPED_UNICODE));
						$redis->ltrim("plugins:" . $key, 0, 99);
					}
				}
				if($terminal->host_uptime!=$json->uptime)
				{
					$alert = array('terminal' => $terminal, 'category' => 1, 'type' => 'startup', 'item' => 1, 'pftime' => time(), 'eptime' => $json->host_time, 'epnano' => 0, 'content' => '终端开机 本次开机时间: ' . date("Y-m-d H:i:s", $json->uptime) . ' 上次开机时间: ' . date("Y-m-d H:i:s", $terminal->host_uptime));
					$redis->lpush("alerts", json_encode($alert, JSON_UNESCAPED_UNICODE));
				}
				
				$redis->expire("plugins:update:" . $terminal->uuid, 300);
                $redis->close();
                
				$terminal->act_time = date("Y-m-d H:i:s", time());
				$terminal->host_uptime = $json->uptime;
				if($json->ver != $terminal->version)
				{
					$terminal->version = $json->ver;
				}
				if(property_exists($json, 'hostinfo_up'))
				{
					if(property_exists($json->hostinfo_up, 'ip'))
						$terminal->host_ip = $json->hostinfo_up->ip;
					if(property_exists($json->hostinfo_up, 'name'))
						$terminal->host_name = $json->hostinfo_up->name;
					if(property_exists($json->hostinfo_up, 'os'))
						$terminal->host_os = $json->hostinfo_up->os;
					if(property_exists($json->hostinfo_up, 'version'))
						$terminal->host_version = $json->hostinfo_up->version;
					if(property_exists($json->hostinfo_up, 'bits'))
						$terminal->host_bits = $json->hostinfo_up->bits;
				}
				$terminal->save();
				
                # todo get task info
                $task = $terminal->tasks()->where('task.status', 1)->order('task.update_at', 'asc')->wherePivot('status', '=', 0)->find();
                if($task)
                {
                    $model = new TaskTerminal();
                    $model->where(['task_id' => $task->id, 'terminal_id' => $terminal->id])->update(['update_at' => date("Y-m-d H:i:s", time()), 'status' => 1]);
					
					return json(['code' => 0, 'message' => '', 'token' => strtolower($token->token), 'timestmap' => $token->time,  'seq' => $next_seq, 'ms_code' => $ms_code, 'task_id' => $task->uuid, 'task_type' => $task->type, 'task_is_debug' => $task->is_debug, 'task_is_log' => $task->is_log, 'task_is_error' => $task->is_error]);
                }
                else
                {
					return json(['code' => 0, 'message' => '', 'token' => strtolower($token->token), 'timestmap' => $token->time, 'seq' => $next_seq, 'ms_code' => $ms_code]);
                }
            }
            else
            {
                return json(['code' => -2, 'message' => 'The terminal not registered.']);
            }
        }
        else
        {
            return json(['code' => -4, 'message' => 'param defect.']);
        }
    }
    
    public function uploadStatus()
    {
        $data = file_get_contents('php://input');
        $json = json_decode($data);
        if(is_null($json))
        {
            print_r('{"code":"error", "message":"param error."}');
            return;
        }
        
        if(property_exists($json, 'objid')==false || property_exists($json, 'taskid')==false)
        {
            print_r('{"code":"error", "message":"param error."}');
            return;
        }
        
        $model = new Terminal();
        $terminal = $model->getByUuid($json->objid);
        if(is_null($terminal))
        {
            print_r('{"code":"error", "message":"The terminal not registered."}');
            return;
        }
		
		$terminal->act_time = date("Y-m-d H:i:s", time());
        $terminal->save();
		
        $model = new Task();
        $task = $model->getByUuid($json->taskid);
        if(is_null($task))
        {
            print_r('{"code":"error", "message":"The task does not exist."}');
            return;
        }
        
        $sql = '';
        $model = new TaskTerminal();
        if(property_exists($json, 'status'))
        {
            $sql = 'update task_terminal set status=' . $json->status . ', update_at=FROM_UNIXTIME(' . time() . ') where `task_id`=\'' . $task->id . '\' and `terminal_id`=\'' . $terminal->id . '\';';
        }
        else
        {
            $sql = 'update task_terminal set update_at=FROM_UNIXTIME(' . time() . ') where `task_id`=\'' . $task->id . '\' and `terminal_id`=\'' . $terminal->id . '\';';
        }
        Db::execute($sql);
        
        $json->type = "status";
        $json->pf_time = time();
        print_r('{"code": "ok", "message": ""}');
        
        $redis=new Redis();
        $redis->connect($this->redis_server, $this->redis_port, 5);
        $redis->lpush("tasklog", json_encode($json, JSON_UNESCAPED_UNICODE));
        $redis->close();
    }
    
    public function updateLog()
    {
        $data = file_get_contents('php://input');
        $json = json_decode($data);
        if(is_null($json))
        {
            print_r('{"code":"error", "message":"param error."}');
            return;
        }
        
        $redis=new Redis();
        $redis->connect($this->redis_server, $this->redis_port, 5);
        $redis->close();
		
        print_r('{"code": "ok", "message": ""}');
    }
	
    public function uploadLog()
    {
        $data = file_get_contents('php://input');
        $json = json_decode($data);
        if(is_null($json))
        {
            print_r('{"code":"error", "message":"param error."}');
            return;
        }
        
        if(property_exists($json, 'objid')==false || property_exists($json, 'taskid')==false)
        {
            print_r('{"code":"error", "message":"param error."}');
            return;
        }
		
		$terminal = new Terminal();
        $terminal->save(['act_time' => date("Y-m-d H:i:s", time())], ['uuid' => $json->objid]);
        
        $json->type = "log";
        $json->pf_time = time();
        print_r('{"code": "ok", "message": ""}');
        
        $redis=new Redis();
        $redis->connect($this->redis_server, $this->redis_port, 5);
        $redis->lpush("tasklog", json_encode($json, JSON_UNESCAPED_UNICODE));
        $redis->close();
    }
    
    public function uploadError()
    {
        $data = file_get_contents('php://input');
        $json = json_decode($data);
        if(is_null($json))
        {
            print_r('{"code":"error", "message":"param error."}');
            return;
        }
        
        if(property_exists($json, 'objid')==false || property_exists($json, 'taskid')==false)
        {
            print_r('{"code":"error", "message":"param error."}');
            return;
        }
		
		$terminal = new Terminal();
        $terminal->save(['act_time' => date("Y-m-d H:i:s", time())], ['uuid' => $json->objid]);
        
        $json->type = "error";
        $json->pf_time = time();
        print_r('{"code": "ok", "message": ""}');
        
        $redis=new Redis();
        $redis->connect($this->redis_server, $this->redis_port, 5);
        $redis->lpush("tasklog", json_encode($json, JSON_UNESCAPED_UNICODE));
        $redis->close();
    }
	
	private function getToken($objid, $timestmap)
    {
		$uri = 'http://127.0.0.1:8080/getToken?objid=' . $objid . '&time=' . $timestmap;
		$headers = [
			'Content-Type' => 'application/json'
		];
		$ret = json_decode($this->curl_request($uri, null, $headers));
		return $ret;
    }
	
	private function curl_request($url, $data = null, $headers = null)
	{
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
		// CURLOPT_HEADER => true,             // 将头文件的信息作为数据流输出
		// CURLOPT_NOBODY => false,            // true 时将不输出 BODY 部分。同时 Mehtod 变成了 HEAD。修改为 false 时不会变成 GET。
		// CURLOPT_CUSTOMREQUEST => $request->method,  // 请求方法
		if(!empty($data)){
			curl_setopt($ch, CURLOPT_POST, 1);
			if (is_array($data)) {
				$data = json_encode($data);
			}
			curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
		}
		if(!empty($headers)){
			curl_setopt($ch, CURLOPT_HTTPHEADER, $this->buildHeaders($headers));
		}
		$output = curl_exec($ch);
		// $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		// $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
		curl_close($ch);
		return $output;
	}

	function buildHeaders($headers)
	{
		$headersArr = array();
		foreach ($headers as $key => $value) {
			array_push($headersArr, "{$key}: {$value}");
		}
		return $headersArr;
	}
}
