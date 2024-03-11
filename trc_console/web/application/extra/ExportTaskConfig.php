<?php
//配置文件

return [
	'Type'  => [
		'0' => '未知',
		'1' => '主机资源使用',
		'2' => '主机性能指标',
		'3' => '进程资源使用',
		'4' => 'TCP连接记录',
		'5' => '提权进程检测'
	],
	'Tool'  => [
		'0' => 'yes',
		'1' => 'plugins-sysperf',
		'2' => 'plugins-systat',
		'3' => 'plugins-procperf',
		'4' => 'plugins-tcpmon',
		'5' => 'plugins-privesccheck'
	],
	'Status'  => [
		'0' => '待执行',
		'1' => '执行中',
		'2' => '执行完成',
		'3' => '执行异常'
	]
];