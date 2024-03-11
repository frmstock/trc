<?php
namespace app\model;

use think\Model;

class Task extends Model
{
	public function enterprise()
    {
        return $this->belongsTo('enterprise', 'enterprise_id', 'id')->find();
    }
	
	public function terminals()
    {
        return $this->belongsToMany('Terminal', 'task_terminal')->order('act_time', 'desc');
    }
	
	public function terminals2()
    {
        return $this->belongsToMany('Terminal', 'task_terminal')->order('pivot.update_at', 'desc');
    }
	
    public function getByUuid($uuid)
	{
		$row = $this->where('uuid', $uuid)->find();
		return $row;
	}
	
    public function getByEnterprise($entid)
	{
		$rows = $this->where('enterprise_id', $entid)->where(['type' => [['eq', 1], ['eq', 2], 'or']])->order('id', 'desc')->limit(0, 10)->select();
		return $rows;
	}
	
    public function getByEnterprisePlg($entid)
	{
		$rows = $this->where('enterprise_id', $entid)->where(['type' => [['eq', 3], ['eq', 4], 'or']])->order('id', 'desc')->limit(0, 10)->select();
		return $rows;
	}
}
