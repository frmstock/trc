<?php
namespace app\model;

use think\Model;

class ArpHosts extends Model
{
	public function enterprise()
    {
        return $this->belongsTo('enterprise', 'enterprise_id', 'id')->find();
    }
	
	public function terminal()
    {
        return $this->belongsTo('terminal', 'terminal_id', 'id')->find();
    }
	
	public function arplogs()
    {
        return $this->hasMany('arp_log', 'arp_id', 'id');
    }
	
    public function getByEnterprise($entid)
	{
		$rows = $this->where('enterprise_id', $entid)->order('id', 'desc')->limit(0, 50)->select();
		return $rows;
	}
	
    public function getNewHosts($entid)
	{
		$rows = $this->where('enterprise_id', $entid)->whereNull('terminal_id')->order('id', 'desc')->limit(0, 20)->select();
		return $rows;
	}
}
