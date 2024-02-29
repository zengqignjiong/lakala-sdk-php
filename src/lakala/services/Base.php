<?php
// +----------------------------------------------------------------------
// | Lakala SDK [Lakala SDK for PHP]
// +----------------------------------------------------------------------
// | Lakala SDK
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: axguowen <axguowen@qq.com>
// +----------------------------------------------------------------------

namespace axguowen\lakala\services;

use axguowen\HttpClient;
use axguowen\lakala\utils\Str;

abstract class Base
{
    // 签名算法
    const SIGNATURE_ALGO = 'LKLAPI-SHA256withRSA';

    /**
     * 接口版本
     * @var string
     */
    protected $apiVersion = '1.0';

    /**
     * 配置参数
     * @var string
     */
    protected $options = [];

    /**
     * 生产环境接口基础Url
     * @var string
     */
    protected $baseUrl = 'https://s2.lakala.com';

    /**
     * 测试环境接口基础Url
     * @var string
     */
    protected $baseUrlTest = 'https://test.wsmsd.cn/sit';

    /**
     * 构造方法
     * @access public
     * @param array $options 配置参数
     * @return void
     */
    public function __construct($options)
    {
        $this->options = array_merge($this->options, $options);
    }

    /**
     * 获取接口地址
     * @access protected
     * @return string
     */
    protected function getBaseUrl()
    {
        // 如果是测试环境
        if (true === $this->options['test_env']) {
            return $this->baseUrlTest;
        }
        // 生产环境
        return $this->baseUrl;
    }

    /**
     * 获取签名鉴权信息
     * @access protected
     * @param string $body
     * @return string
     */
	protected function getAuthorization($body = '')
    {
        // 生成12位随机字符串
		$nonceStr = Str::random(12);
        // 请求时间戳
     	$timestamp = time();
        // 获取配置中的APPID
        $appid = $this->options['appid'];
        // 获取配置中的SERIAL_NO
        $serialNo = $this->options['serial_no'];
        // 构造签名报文
      	$message = $appid . "\n" . $serialNo . "\n" . $timestamp . "\n" . $nonceStr . "\n" . $body . "\n";
        // 获取私钥
        $key = openssl_get_privatekey($this->options['private_key']);
        // 签名
        openssl_sign($message, $signature, $key, OPENSSL_ALGO_SHA256);
        // 释放密钥资源
        openssl_free_key($key);
        // 拼接并返回鉴权信息
        return static::SIGNATURE_ALGO . ' ' . Str::serializeAuthData([
            'appid' => $appid,
            'serial_no' => $serialNo,
            'timestamp' => $timestamp,
            'nonce_str' => $nonceStr,
            'signature' => base64_encode($signature),
        ]);
	}

    /**
     * 校验签名
     * @access protected
     * @param string $authorization
     * @param string $body
     * @return bool
     */
	protected function signatureVerification($authorization, $body = '')
    {
        // 过滤算法标识
        $authorization = trim(str_replace(static::SIGNATURE_ALGO, '', $authorization));
        // 反序列化获取鉴权字段
        $authData = Str::unserializeAuthData($authorization);
        // 构造签名报文
        $message = $authData['timestamp'] . "\n" . $authData['nonce_str'] . "\n" . $body . "\n";
        // 获取公钥
        $key = openssl_get_publickey($this->options['certificate']);
        // 获取校验结果
        $flag = openssl_verify($message, base64_decode($authData['signature']), $key, OPENSSL_ALGO_SHA256);
        // 释放密钥资源
        openssl_free_key($key);
        // 正确
        if($flag) {
            return true;
        }
        return false;
    }

    /**
     * 发送POST请求
     * @access protected
     * @param string $path 请求接口
     * @param array $body 请求参数
     * @return array
     */
    protected function post($path, array $body = [])
    {
        $body = json_encode($body, JSON_UNESCAPED_UNICODE);
        // 获取鉴权信息
        $authorization = $this->getAuthorization($body);
        // 构造请求头
        $headers = [
            'Authorization' => $authorization,
            'Content-Type' => 'application/json;charset=utf-8',
            'Accept' => 'application/json',
        ];
        // 发送请求
        $ret = HttpClient::post($this->getBaseUrl() . $path, $body, $headers);
        if (!$ret->ok()) {
            throw new \Exception($ret->error, $ret->statusCode);
        }
        // 如果响应体为空
        if(!is_null($ret->body)){
            return $ret->json();
        }
        return [];
    }

    /**
     * 发送GET请求
     * @access protected
     * @param string $path 请求接口
     * @param array $query 请求参数
     * @return array
     */
    protected function get($path, array $query = [])
    {
        // 如果请求参数不为空
        if(!empty($query)){
            // 拼接请求参数
            $path .= (false === strpos($path, '?') ? '?' : '&') . http_build_query($query);
        }
        // 获取鉴权信息
        $authorization = $this->getAuthorization();
        // 构造请求头
        $headers = [
            'Authorization' => $authorization,
        ];
        // 发送请求
        $ret = HttpClient::get($this->getBaseUrl() . $path, $headers);
        if (!$ret->ok()) {
            throw new \Exception($ret->error, $ret->statusCode);
        }
        // 如果响应体为空
        if(!is_null($ret->body)){
            return $ret->json();
        }
        return [];
    }

    /**
     * 发送PUT请求
     * @access protected
     * @param string $path 请求接口
     * @param array $body 请求参数
     * @return array
     */
    protected function put($path, array $body = [])
    {
        $body = json_encode($body, JSON_UNESCAPED_UNICODE);
        // 获取鉴权信息
        $authorization = $this->getAuthorization($body);
        // 构造请求头
        $headers = [
            'Authorization' => $authorization,
            'Content-Type' => 'application/json;charset=utf-8',
            'Accept' => 'application/json',
        ];
        // 发送请求
        $ret = HttpClient::put($this->getBaseUrl() . $path, $body, $headers);
        if (!$ret->ok()) {
            throw new \Exception($ret->error, $ret->statusCode);
        }
        // 如果响应体为空
        if(!is_null($ret->body)){
            return $ret->json();
        }
        return [];
    }

    /**
     * 发送PATCH请求
     * @access protected
     * @param string $path 请求接口
     * @param array $body 请求参数
     * @return array
     */
    protected function patch($path, array $body = [])
    {
        $body = json_encode($body, JSON_UNESCAPED_UNICODE);
        // 获取鉴权信息
        $authorization = $this->getAuthorization($body);
        // 构造请求头
        $headers = [
            'Authorization' => $authorization,
            'Content-Type' => 'application/json;charset=utf-8',
            'Accept' => 'application/json',
        ];
        // 发送请求
        $ret = HttpClient::patch($this->getBaseUrl() . $path, $body, $headers);
        if (!$ret->ok()) {
            throw new \Exception($ret->error, $ret->statusCode);
        }
        // 如果响应体为空
        if(!is_null($ret->body)){
            return $ret->json();
        }
        return [];
    }

    /**
     * 发送DELETE请求
     * @access protected
     * @param string $path 请求接口
     * @param array $query 请求参数
     * @return array
     */
    protected function delete($path, array $query = [])
    {
        // 如果请求参数不为空
        if(!empty($query)){
            // 拼接请求参数
            $path .= (false === strpos($path, '?') ? '?' : '&') . http_build_query($query);
        }
        // 获取鉴权信息
        $authorization = $this->getAuthorization();
        // 构造请求头
        $headers = [
            'Authorization' => $authorization,
        ];
        // 发送请求
        $ret = HttpClient::delete($this->getBaseUrl() . $path, $headers);
        if (!$ret->ok()) {
            throw new \Exception($ret->error, $ret->statusCode);
        }
        // 如果响应体为空
        if(!is_null($ret->body)){
            return $ret->json();
        }
        return [];
    }
}
