<?php
//配置文件

use think\Env;

return [
    'redis_server'        => Env::get('redis_server', '127.0.0.1'),
    'redis_port'          => Env::get('redis_port',   '6379')
];