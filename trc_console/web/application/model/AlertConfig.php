<?php
namespace app\model;

use think\Model;

class AlertConfig extends Model
{
	public function enterprise()
    {
        return $this->belongsTo('enterprise', 'enterprise_id', 'id')->find();
    }
	
	public function terminal()
    {
        return $this->belongsTo('terminal', 'terminal_id', 'id');
    }
	
	public function alertConfigDetails()
    {
        return $this->hasMany('alertConfigDetail', 'alert_config_id', 'id');
    }
	
    public function getByEnterprise($entid)
	{
		$rows = $this->where('enterprise_id', $entid)->with(["terminal" => function ($query) {$query->withField(['id', 'uuid', 'act_time', 'host_ip', 'host_os', 'host_name', 'host_version']);}])->with("alertConfigDetails")->limit(0, 20)->select();
		return $rows;
	}
	
    public function findOne($entid, $terid)
	{
		$row = $this->where(['enterprise_id' => $entid, 'terminal_id' => $terid])->find();
		return $row;
	}
}
