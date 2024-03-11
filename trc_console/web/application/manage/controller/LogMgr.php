<?php
namespace app\manage\controller;

use think\Env;
use think\Request;
use think\Session;
use Elasticsearch\ClientBuilder;

use app\common\FrmController;
use app\model\Task;
use app\model\TaskTerminal;
use app\model\Terminal;

class LogMgr extends FrmController
{
    private $client;
    public function _initialize()
    {
		parent::_initialize();
		
		try
		{
			$this->client = ClientBuilder::create()->setHosts([
				[
					'host'   => config('es_server'),
					'port'   => config('es_port'),
					'scheme' => 'http',
				]
			])->build();
		}
		catch(Exception $e)
		{
			print_r($e);
		}
    }
	
    public function getList(Request $request)
    {
		$objid = $request->param('objid');
		$uuid = $request->param('uuid');
		
		$params = [
			'index' => $uuid,
			'body'  => [
				'query' => [
					'bool' => [
						'must' => [
							[
								'match_phrase' => [
									'objid' => [
										'query' => $objid
									]
								]
							]
						]
					]
				],
				'from' => 0,
				'size' => 100,
				'sort' => [
					[
						'time' => 'asc'
					],             
					[     
						'nanosecond' => 'asc'
					]                        
				]
			]
		];
		
		$response = $this->client->search($params);
		return json(['status' => 0, 'message' => '', 'result' => $response['hits']['hits']]);
    }
	
	public function getTerminalLog(Request $request)
    {
		$objid = $request->param('objid');
		
		$params = [
			'ignore_unavailable' => true,
			'index' => $this->genIndexName('log-terminal', 7),
			'body'  => [
				'query' => [
					'bool' => [
						'must' => [
							[
								'match_phrase' => [
									'objid' => [
										'query' => $objid
									]
								]
							]
						]
					]
				],
				'from' => 0,
				'size' => 100,
				'sort' => [
					[
						'pftime' => 'desc'
					],             
					[     
						'epnano' => 'desc'
					]                        
				]
			]
		];
		
		$response = $this->client->search($params);
		/*
		for($i=0, $count=count($response['hits']['hits']); $i<$count; $i++)
		{
			$response['hits']['hits'][$i] = $response['hits']['hits'][$i]['_source'];
		}*/
		$result = array();
		foreach($response['hits']['hits'] as $key => $value)
		{
			$result[$key] = $value['_source'];
			$result[$key]['type'] = config('TerminalLogType.category')[$result[$key]['type']];
		}
		
		return json(['status' => 0, 'message' => '', 'result' => $result]);
    }
	
	private function genIndexName($prefix, $number)
	{
		$cur_time = time();
		$indices = $prefix . "-" . date("Ymd", $cur_time);
		
		$i=1;
		while($i<$number)
		{
			$i=$i+1;
			$cur_time = $cur_time-86400;
			$indices = $indices . "," . $prefix . "-" . date("Ymd", $cur_time);
		}
		
		return $indices;
	}
	
	public function getPluginsSysperf(Request $request)
    {
		$objid = $request->param('objid');
		
		$params = [
			'ignore_unavailable' => true,
			'index' => $this->genIndexName('plugins-sysperf', 9),
			'body'  => [
				'query' => [
					'bool' => [
						'must' => [
							[
								'match_phrase' => [
									'objuuid' => [
										'query' => $objid
									]
								]
							]
						]
					]
				],
				'from' => 0,
				'size' => 100,
				'sort' => [
					[
						'pftime' => 'desc'
					]                     
				],
				'_source' => ['cpu_idle', 'cpu_rate', 'eptime', 'mem_rate', 'net_rx', 'net_tx', 'objuuid', 'pftime', 'procs']
			]
		];
		
		$response = $this->client->search($params);
		return json(['status' => 0, 'message' => '', 'result' => $response['hits']['hits']]);
    }
	
	public function getPluginsSystat(Request $request)
    {
		$objid = $request->param('objid');
		
		$params = [
			'ignore_unavailable' => true,
			'index' => $this->genIndexName('plugins-systat', 9),
			'body'  => [
				'query' => [
					'bool' => [
						'must' => [
							[
								'match_phrase' => [
									'objuuid' => [
										'query' => $objid
									]
								]
							]
						]
					]
				],
				'from' => 0,
				'size' => 100,
				'sort' => [
					[
						'pftime' => 'desc'
					]                     
				],
				'_source' => ['cpu_id', 'cpu_st', 'cpu_sy', 'cpu_us', 'cpu_wa', 'eptime', 'forks', 'io_bi', 'io_bo', 'mem_buff', 'mem_cache', 'mem_free', 'mem_swpd', 'objuuid', 'pftime', 'procs_b', 'procs_r', 'swap_si', 'swap_so', 'system_cs', 'system_in']
			]
		];
		
		$response = $this->client->search($params);
		return json(['status' => 0, 'message' => '', 'result' => $response['hits']['hits']]);
    }
	
	public function getPluginsProcperf(Request $request)
    {
		$objid = $request->param('objid');
		
		$params = [
			'ignore_unavailable' => true,
			'index' => $this->genIndexName('plugins-procperf', 9),
			'body'  => [
				'query' => [
					'bool' => [
						'must' => [
							[
								'match_phrase' => [
									'objuuid' => [
										'query' => $objid
									]
								]
							]
						]
					]
				],
				'from' => 0,
				'size' => 100,
				'sort' => [
					[
						'pftime' => 'desc'
					]                     
				],
				'_source' => ['VmHWM', 'VmRSS', 'cpu', 'eptime', 'handler', 'mem', 'name', 'objuuid', 'path', 'pftime', 'pid', 'ppid', 'start', 'threads']
			]
		];
		
		$response = $this->client->search($params);
		return json(['status' => 0, 'message' => '', 'result' => $response['hits']['hits']]);
    }
	
	public function getPluginsTcpmon(Request $request)
    {
		$objid = $request->param('objid');
		
		$params = [
			'ignore_unavailable' => true,
			'index' => $this->genIndexName('plugins-tcpmon', 9),
			'body'  => [
				'query' => [
					'bool' => [
						'must' => [
							[
								'match_phrase' => [
									'objuuid' => [
										'query' => $objid
									]
								]
							]
						]
					]
				],
				'from' => 0,
				'size' => 100,
				'sort' => [
					[
						'pftime' => 'desc'
					]                     
				],
				'_source' => ['action', 'eptime', 'local_ip', 'local_port', 'objuuid', 'pftime', 'remote_ip', 'remote_port', 'status_str']
			]
		];
		
		$response = $this->client->search($params);
		return json(['status' => 0, 'message' => '', 'result' => $response['hits']['hits']]);
    }
	
	public function getPluginsPrivesccheck(Request $request)
    {
		$objid = $request->param('objid');
		
		$params = [
			'ignore_unavailable' => true,
			'index' => $this->genIndexName('plugins-privesccheck', 9),
			'body'  => [
				'query' => [
					'bool' => [
						'must' => [
							[
								'match_phrase' => [
									'objuuid' => [
										'query' => $objid
									]
								]
							]
						]
					]
				],
				'from' => 0,
				'size' => 100,
				'sort' => [
					[
						'pftime' => 'desc'
					]                     
				],
				'_source' => ['action', 'cmdline', 'eptime', 'euid', 'euser', 'exe', 'name', 'objuuid', 'pftime', 'pid', 'ppid', 'ruid', 'ruser', 'state']
			]
		];
		
		$response = $this->client->search($params);
		return json(['status' => 0, 'message' => '', 'result' => $response['hits']['hits']]);
    }
	
	public function getPluginsCntrpm(Request $request)
    {
		$objid = $request->param('objid');
		
		$params = [
			'ignore_unavailable' => true,
			'index' => $this->genIndexName('plugins-cntrpm', 9),
			'body'  => [
				'query' => [
					'bool' => [
						'must' => [
							[
								'match_phrase' => [
									'objuuid' => [
										'query' => $objid
									]
								]
							]
						]
					]
				],
				'from' => 0,
				'size' => 100,
				'sort' => [
					[
						'pftime' => 'desc'
					]                     
				],
				'_source' => ['action', 'eptime', 'objuuid', 'pftime', 'pid', 'VmHWM', 'VmRSS', 'cmdline', 'exepath', 'hash', 'name', 'ppid', 'threads', 'workdir']
			]
		];
		
		$response = $this->client->search($params);
		return json(['status' => 0, 'message' => '', 'result' => $response['hits']['hits']]);
    }
	
	public function getPluginsCntrec(Request $request)
    {
		$objid = $request->param('objid');
		
		$params = [
			'ignore_unavailable' => true,
			'index' => $this->genIndexName('plugins-cntrec', 9),
			'body'  => [
				'query' => [
					'bool' => [
						'must' => [
							[
								'match_phrase' => [
									'objuuid' => [
										'query' => $objid
									]
								]
							]
						]
					]
				],
				'from' => 0,
				'size' => 100,
				'sort' => [
					[
						'pftime' => 'desc'
					]                     
				],
				'_source' => ['action', 'eptime', 'local_ip', 'local_port', 'objuuid', 'pftime', 'remote_ip', 'remote_port', 'status_str']
			]
		];
		
		$response = $this->client->search($params);
		return json(['status' => 0, 'message' => '', 'result' => $response['hits']['hits']]);
    }
}
