<?php
namespace app\model;

use think\Model;

class AlertConfigDetail extends Model
{
	public function alertConfig()
    {
        return $this->belongsTo('alertConfig', 'alert_config_id', 'id')->find();
    }
}
