<?php
namespace app\common;
 
use think\Validate;
 
class UserValidate extends Validate
{
	protected $regex = [ 'username' => '[A-Za-z][A-Za-z0-9_]+',									//字母开头
	                     'email' => '^[a-zA-Z0-9_-]+@[a-zA-Z0-9_-]+(\.[a-zA-Z0-9_-]+)+$'];
	
    /**
     * 定义验证规则
     * 格式：'字段名'	=>	['规则1','规则2'...]
     *
     * @var array
     */
    protected $rule = [
        'username'  =>  'require|length:6,20|unique:user|regex:username',
        'password'  =>  'require|alphaNum|length:6,20',
        'confirm_password' => 'require|confirm:password',
        'code'      =>  'require',
        'email'     =>  'require|email|regex:email'
    ];
	
    /**
     * 定义错误信息
     * 格式：'字段名.规则名'	=>	'错误信息'
     *
     * @var array
     */
    protected $message = [
        'id.require'        =>  'id不正确',
        'username.require'  =>  '用户名不能为空',
        'username.unique'   =>  '用户名必须唯一',
        'username.length'   =>  '用户名长度在6~20个字符之内',
		'username.regex'    =>  '用户名必须是数字、字母或下划线，且字母开头!',
        'password.require'  =>  '密码不能为空',
        'password.length'   =>  '密码长度在6~20个字符之内',
        'password.alphaNum' =>  '密码必须是数字字母',
        'confirm_password.require'  =>  '确认密码不能为空',
        'confirm_password.confirm'  =>  '两次密码输入不相同',
        'code.require'              =>  '验证码不能为空',
        'email.require'     =>  '邮箱不能为空',
        'email.email'       =>  '邮箱格式不正确',
        'email.unique'      =>  '该邮箱已存在'
    ];

    protected $scene = [
        'login' =>  ['username' =>  'require', "password" =>  'require'],
        'register'   =>  ['username', 'password', 'confirm_password', 'email'],
        'edit'  =>  ['id']
    ];
}
