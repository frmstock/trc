<?php
namespace app\model;

use think\Model;

class PluginsFiles extends Model
{
	public function plugins()
    {
        return $this->belongsTo('plugins', 'plugins_version', 'version');
    }
	
    public function getByPlugins($version)
	{
		$rows = $this->where('plugins_version', $version)->select();
		return $rows;
	}
}
