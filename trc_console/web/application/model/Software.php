<?php
namespace app\model;

use think\Model;

class Software extends Model
{
	public function terminal()
    {
        return $this->belongsTo('terminal', 'terminal_id', 'id');
    }
	
    public function getByTerminal($objid)
	{
		$rows = $this->where('terminal_id', $objid)->order('install_time', 'desc')->select();
		return $rows;
	}
}
