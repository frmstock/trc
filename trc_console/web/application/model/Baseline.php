<?php
namespace app\model;

use think\Model;

class Baseline extends Model
{
	public function terminal()
    {
        return $this->belongsTo('terminal', 'terminal_id', 'id');
    }
	
    public function getByTerminal($objid)
	{
		$rows = $this->where('terminal_id', $objid)->order('item', 'asc')->select();
		return $rows;
	}
}
