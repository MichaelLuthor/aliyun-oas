<?php
namespace Sige\Lib\AliyunOAS;
class HttpRequest {
    /** 请求方式 */
    private $method = 'GET';
    /** 请求URL */
    private $url = null;
    /** 请求头部信息 */
    private $headers = array(
        # 'key' => 'value',
    );
    /** 请求参数信息 */
    private $params = array();
    /** 原始的post数据 */
    private $rawPostData = false;
    
    /**
     * @param string $url
     * @param string $method
     */
    public function __construct( $url, $method='GET' ) {
        $this->url = $url;
        $this->method = $method;
    }
    
    /** 设置请求URL */
    public function setUrl( $url ) {
        $this->url = $url;
    }
    
    /** 获取当前请求URL */
    public function getUrl() {
        $url = $this->url;
        if ( 'GET' === $this->method ) {
            if ( !empty($this->params) ) {
                $connector = strpos($url, '?') ? '&' : '?';
                $url = $url.$connector.http_build_query($this->params);
            }
        }
        return $url;
    }
    
    /** 获取请求不带参数的URL */
    public function getBaseUrl() {
        return $this->url;
    }
    
    /** 获取当前请求方法 */
    public function getMethod() {
        return $this->method;
    }
    
    /** 设置请求参数 */
    public function setParams( $params ) {
        $this->params = $params;
    }
    
    /** 设置参数 */
    public function setParam( $name, $value ) {
        $this->params[$name] = $value;
    }
    
    /** 获取请求参数 */
    public function getParams() {
        return $this->params;
    }
    
    /** 设置原始的Post数据 */
    public function setRawPostData( $data ) {
        $this->rawPostData = $data;
    }
    
    /**
     * 设置请求头
     * @param string $name
     * @param string $value
     */
    public function headerSet($name, $value) {
        $this->headers[$name] = $value;
    }
    
    /**
     * 获取所有的header
     * @return array
     */
    public function headerToArray() {
        return $this->headers;
    }
    
    public function headerClear() {}
    public function headerSetValues() {}
    
    /** 
     * 执行请求
     * @return \Sige\Lib\AliyunOAS\HttpRequest
     * */
    public function execute() {
        $headers = array();
        foreach ( $this->headers as $key => $value ) {
            $headers[] = "$key: $value";
        }
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->getUrl());
        if ( 'GET' === $this->method ) {
           # nothing to do here.
        } else if ( 'POST' === $this->method ) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $this->rawPostData 
                ? $this->rawPostData : json_encode($this->params));
        } else {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $this->method);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $this->params);
        }
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $this->setupResponse($ch, $response);
        curl_close($ch);
        return $this;
    }
    
    /** 请求内容 */
    private $responseContent = null;
    /** 请求错误代码 */
    private $responseErrorCode = null;
    /** 请求错误消息 */
    private $responseErrorMessage = null;
    /** 请求返回头部信息 */
    private $responseHeaders = array();
    
    /** 填充返回信息 */
    private function setupResponse( $ch, $response ) {
        $headerSize = curl_getinfo($ch,CURLINFO_HEADER_SIZE);
        $responseHeaders = substr($response, 0, $headerSize);
        $responseHeaders = explode("\n", $responseHeaders);
        $this->responseHeaders = array();
        foreach ( $responseHeaders as $responseHeader ) {
            if ( false === strpos($responseHeader, ': ') ) {
                continue;
            }
            list($key, $value) = explode(': ', $responseHeader);
            $this->responseHeaders[$key] = trim($value);
        }
        
        $this->responseContent = substr($response, $headerSize);
        $this->responseErrorCode = curl_errno($ch);
        $this->responseErrorMessage = curl_error($ch);
    }
    
    /**
     * 获取请求结果
     * @return mixed
     */
    public function response() {
        return $this->responseContent;
    }
    
    /**
     * 获取JSON格式请求结果
     * @return array
     */
    public function responseJson() {
        return json_decode($this->responseContent, true);
    }
    
    /**
     * 获取指定返回头部信息
     * @param string $key
     * @return mixed
     */
    public function responseHeader( $key ) {
        return $this->responseHeaders[$key];
    }
}