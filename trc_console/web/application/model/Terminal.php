<?php
namespace app\model;

use think\Model;

class Terminal extends Model
{
	public function enterprise()
    {
        return $this->belongsTo('enterprise', 'enterprise_id', 'id');
    }
	
	public function tasks()
    {
        return $this->belongsToMany('Task', 'task_terminal');
    }
	
	public function baselines()
    {
        return $this->hasMany('baseline', 'terminal_id', 'id');
    }
	
    public function getByUuid($uuid)
	{
		$row = $this->where('uuid', $uuid)->find();
		return $row;
	}
	
    public function getCountByEnterprise($entid)
	{
		$count = $this->where('enterprise_id', $entid)->count();
		return $count;
	}
	
    public function getByEnterprise($entid)
	{
		$rows = $this->where('enterprise_id', $entid)->order('act_time', 'desc')->limit(0, 20)->select();
		return $rows;
	}
}
