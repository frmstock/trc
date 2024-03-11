<?php
namespace app\manage\controller;

use think\Request;
use think\Session;

use app\common\FrmController;

class System extends FrmController
{
	function getlicense(Request $request)
	{
		$uri = 'http://127.0.0.1:8080';
		$headers = [
			'Content-Type' => 'application/json'
		];
		$ret = json_decode($this->curl_request($uri, null, $headers));
		return json($ret);
    }
	
	private function curl_request($url, $data = null, $headers = null)
	{
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
		// CURLOPT_HEADER => true,             // 将头文件的信息作为数据流输出
		// CURLOPT_NOBODY => false,            // true 时将不输出 BODY 部分。同时 Mehtod 变成了 HEAD。修改为 false 时不会变成 GET。
		// CURLOPT_CUSTOMREQUEST => $request->method,  // 请求方法
		if(!empty($data)){
			curl_setopt($ch, CURLOPT_POST, 1);
			if (is_array($data)) {
				$data = json_encode($data);
			}
			curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
		}
		if(!empty($headers)){
			curl_setopt($ch, CURLOPT_HTTPHEADER, $this->buildHeaders($headers));
		}
		$output = curl_exec($ch);
		// $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		// $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
		curl_close($ch);
		return $output;
	}

	function buildHeaders($headers)
	{
		$headersArr = array();
		foreach ($headers as $key => $value) {
			array_push($headersArr, "{$key}: {$value}");
		}
		return $headersArr;
	}
}
