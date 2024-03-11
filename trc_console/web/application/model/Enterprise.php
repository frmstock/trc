<?php
namespace app\model;

use think\Model;

class Enterprise extends Model
{
	public function terminals()
    {
        return $this->hasMany('terminal', 'enterprise_id', 'id');
    }
	
	public function tasks()
    {
        return $this->hasMany('task', 'enterprise_id', 'id');
    }
	
	public function users()
    {
        return $this->hasMany('user', 'enterprise_id', 'id');
    }
	
    public function getByUuid($uuid)
	{
		$row = $this->where('uuid', $uuid)->find();
		return $row;
	}
}
