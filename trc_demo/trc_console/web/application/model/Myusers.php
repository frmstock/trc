<?php
namespace app\model;

use think\Model;

class Myusers extends Model
{
	public function terminal()
    {
        return $this->belongsTo('terminal', 'terminal_id', 'id')->find();
    }
	
	public function getMyusers($ter_id)
	{
		$rows = $this->where('terminal_id', $ter_id)->order('id', 'desc')->limit(0, 20)->select();
		return $rows;
	}
}
