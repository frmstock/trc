<?php
namespace app\model;

use think\Model;

class Plugins extends Model
{
	public function pluginsFiles()
    {
        return $this->hasMany('plugins_files', 'plugins_version', 'version');
    }
	
    public function getByVersion($version)
	{
		$row = $this->where('version', $version)->find();
		return $row;
	}
	
    public function getCount()
	{
		$count = $this->count();
		return $count;
	}
	
    public function getAll()
	{
		$rows = $this->order('id', 'desc')->select();
		return $rows;
	}
}
