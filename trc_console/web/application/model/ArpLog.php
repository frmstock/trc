<?php
namespace app\model;

use think\Model;

class ArpLog extends Model
{
	public function terminal()
    {
        return $this->belongsTo('terminal', 'terminal_id', 'id')->find();
    }
	
	public function arphosts()
    {
        return $this->belongsTo('arp_hosts', 'arp_id', 'id')->find();
    }
}
