<?php
namespace app\model;

use Redis;

use think\Model;
use think\Session;

use app\common\UserValidate;
use app\model\Enterprise;
use app\model\Terminal;

class User extends Model
{
	public function eterprise()
    {
        return $this->belongsTo('enterprise', 'enterprise_id', 'id');
    }
	
    public function getByUserName($username)
	{
		$row = $this->where('username', $username)->find();
		return $row;
	}
	
	// 用户登录验证
    public static function register($data)
    {
        $validate = new UserValidate();
        if (!$validate->scene('register')->check($data))
		{
            return json(['status' => -1, 'message' => $validate->getError()]);
        }
		
		$ent = new Enterprise();
		$ent->uuid = uuid_create(1);
        if($ent->save()==false)
        {
            return json(['status' => -1, 'message' => '添加失败']);
        }
		
		$ter = new Terminal();
		$ter->username = $data['username'];
		$ter->email = $data['email'];
		$ter->password = strtoupper(md5($data['password']));
		$ter->reg_time = time();
		$ter->act_time = time();
		$ter->update_at = time();
		$ter->update_at = time();
        if($ent->users()->save($ter)==false)
        {
            return json(['status' => -1, 'message' => '添加失败']);
        }
        else
        {
			$redis=new Redis();
			$redis->connect(config('redis.host'), config('redis.port'), 5);
			$redis->lpush("register", $ent->uuid);
			$redis->close();
            return json(['status' => 0, 'message' => '']);
        }
    }
	
	// 用户登录验证
    public static function login($data)
    {
        $validate = new UserValidate();
        if (!$validate->scene('login')->check($data))
		{
            return json(['status' => -1, 'message' => $validate->getError()]);
        }
		
        $res = self::get(['username' => $data['username']]);
        if (!$res)
		{
            return json(['status' => -2, 'message' => '账号不存在']);
        }
		
        #if (!$res['status'])
		#{
        #    return json(['status' => 0, 'message' => '账号禁用']);
        #}
		
        if (strtoupper($data['password']) == $res['password'])
		{
			$ent = $res->eterprise;
			if (!$ent)
		    {
                return json(['status' => -2, 'message' => '账号不存在']);
            }
            Session('userId', $res['id']);
            Session('username', $res['username']);
            Session('entid', $ent['id']);
            Session('entuuid', $ent['uuid']);
			Session('expire', time()+30*60);
            return json(['status' => 0, 'message' => '登录成功']);
        }
		
        return json(['status' => -3, 'message' => '密码不正确']);
    }
}
