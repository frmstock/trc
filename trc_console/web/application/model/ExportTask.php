<?php
namespace app\model;

use think\Model;

use app\model\Enterprise;

class ExportTask extends Model
{
	public function eterprise()
    {
        return $this->belongsTo('enterprise', 'enterprise_id', 'id');
    }
	
    public function getByUuid($uuid)
	{
		$row = $this->where('uuid', $uuid)->find();
		return $row;
	}
	
    public function getByEnterprise($entid)
	{
		$rows = $this->where('enterprise_id', $entid)->order('create_at', 'desc')->limit(0, 20)->select();
		return $rows;
	}
}
