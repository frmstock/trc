<?php
namespace app\manage\controller;

use think\Request;
use think\Session;

use app\common\FrmController;

class Client extends FrmController
{
	function entid(Request $request)
	{
		$id = $request->param('id');
		$entid = Session::get('entuuid');
		if($id==2)
		{
			$size = 42;
			$content = "entid=" . $entid;
		}
		else
		{
			$size = 36;
			$content = $entid;
		}
		
		header('Content-Type: application/octet-stream');
		header('Accept-Ranges:bytes');
		header('Content-Length:' . $size); //注意是'Content-Length:' 非Accept-Length
		header('Content-Disposition:attachment;filename=entid');//声明作为附件处理和下载后文件的名称
		
		ob_clean();
		echo $content;
		flush();
		exit;

	}
}
