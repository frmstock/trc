<?php
namespace app\model;

use think\model\Pivot;

class TaskTerminal extends Pivot
{
	protected $name = 'task_terminal';
	
	public function terminal()
    {
        return $this->belongsTo('terminal', 'terminal_id', 'id')->find();
    }
	
    public function getOne($objid, $taskid)
	{
		$row = $this->where(['terminal_id' => $objid, 'task_id' => $taskid])->find();
		return $row;
	}
}
