<?php
namespace app\manage\controller;

use app\common\FrmController;
use app\model\User;

class Login extends FrmController
{
	# get session
    public function index()
    {
		session('expire', time()+30*60);
        return $this->redirect('/index.html');
    } 
	
	public function isLogin()
    {
		$_SESSION['frmstock']['expire'] = time()+1800;
        return json(['status' => 0, 'message' => '登录成功']);
    }
	
    public function register()
    {
		$data = input('post.');
        $result = User::register($data);
        return $result;
    }
	
    public function doLogin()
    {
		$data = input('post.');
        $result = User::login($data);
        return $result;
    }
	
    public function logout()
    {
		Session(null);
        return  $this->redirect('/index.html');
    }
}
