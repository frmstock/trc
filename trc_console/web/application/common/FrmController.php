<?php
namespace app\common;

use think\Controller;
use think\response\Json;
use think\Session;

class FrmController extends Controller
{
    public function _initialize()
	{
		$allowUris = [
            '/api/index/test',
            '/manage/login/index',
            '/manage/login/register',
            '/manage/login/dologin'
        ];
		
		if (!in_array(strtolower($_SERVER['PATH_INFO']), $allowUris))
		{
			// 没有session
			if(null==Session::get('expire'))
			{
				exit(Json(['status' => 1, 'message' => '没有session', 'goto' => '/index.html'])->getContent());
			}
			
			// session过期，失效
			if(Session::get('expire')<time())
			{
				exit(Json(['status' => 1, 'message' => '过期失效', 'goto' => '/index.html'])->getContent());
			}
			
			Session('expire', time()+30*60);
			// 没有登录
			if(null==Session::get('userId'))
			{
				exit(Json(['status' => 1, 'message' => '未登录', 'goto' => '/index.html'])->getContent());
			}
        }
	}
}
