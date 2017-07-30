<?php
namespace Sige\Lib\AliyunOAS;
require __DIR__.DIRECTORY_SEPARATOR.'HttpRequest.php';
/**
 * @author Michael Luthor <michaelluthor@163.com>
 * @see https://help.aliyun.com/document_detail/27385.html?spm=5176.doc27384.6.557.XDJ8fX
 */
class AliyunOAS {
    /** API 操作access id */
    private $accessId = null;
    /** API 操作access key */
    private $accessKey = null;
    /** 归档存储 API的服务接入地址 */
    private $host = null;
    
    /**
     * @param string $accessId api 操作access id
     * @param string $accessKey api 操作access key
     * @see https://help.aliyun.com/knowledge_detail/38738.html
     */
    public function __construct($accessId, $accessKey, $host) {
        $this->accessId = $accessId;
        $this->accessKey = $accessKey;
        $this->host = $host;
    }
    
    /**
     * 获取Vault列表
     * @return array
     */
    public function vaultList() {
        $request = new HttpRequest('/vaults', 'GET');
        $response = $this->executeRequest($request);
        
        $vaults = array();
        foreach ( $response['VaultList'] as $vault ) {
            $vault['CreationDate'] = strtotime($vault['CreationDate']);
            $vault['LastInventoryDate'] = strtotime($vault['LastInventoryDate']);
            $vaults[] = $vault;
        }
        return $vaults;
    }
    
    /**
     * 创建Vault
     * @param string $name
     * @return 返回Vault ID。
     */
    public function vaultCreate( $name ) {
        $request = new HttpRequest("/vaults/{$name}", 'PUT');
        $this->executeRequest($request);
        return $request->responseHeader('x-oas-vault-id');
    }
    
    /**
     * 删除Vault
     * @param string $id
     */
    public function vaultDelete( $id ) {
        $request = new HttpRequest("/vaults/{$id}", 'DELETE');
        $this->executeRequest($request);
    }
    
    /**
     * 获取Vault信息
     * @param string $id
     * @return array
     */
    public function vaultGetInfo( $id ) {
        $request = new HttpRequest("/vaults/{$id}", 'GET');
        $response = $this->executeRequest($request);
        $response['CreationDate'] = strtotime($response['CreationDate']);
        $response['LastInventoryDate'] = strtotime($response['LastInventoryDate']);
        return $response;
    }
    
    /**
     * 上传Archive
     * @param string $vaultId
     * @param string $name
     * @param string $filepath
     * @return 上传文档的Archive ID
     */
    public function archiveUpload( $vaultId, $name, $filepath ) {
        $request = new HttpRequest("/vaults/{$vaultId}/archives", 'POST');
        $request->headerSet('Content-Length', filesize($filepath));
        $request->headerSet('x-oas-archive-description', $name);
        $request->headerSet('x-oas-content-etag', strtoupper(md5_file($filepath)));
        $request->headerSet('x-oas-tree-etag', $this->getFileTreeHash($filepath));
        $request->setRawPostData(file_get_contents($filepath));
        
        $response = $this->executeRequest($request);
        return $request->responseHeader('x-oas-archive-id');
    }
    
    /**
     * 删除Archive
     * @param string $vaultId 
     * @param string $archiveId
     */
    public function archiveDelete( $vaultId, $archiveId) {
        $request = new HttpRequest("/vaults/{$vaultId}/archives/{$archiveId}", 'DELETE');
        $this->executeRequest($request);
    }
    
    /**
     * 启动Archive列表任务
     * @param string $vaultId
     */
    public function jobStartInventoryRetrieval( $vaultId ){
        $request = new HttpRequest("/vaults/{$vaultId}/jobs", 'POST');
        $request->setParam('Type', 'inventory-retrieval');
        $request->setParam('Description', "List Archives Of {$vaultId}");
        $this->executeRequest($request);
        return $request->responseHeader('x-oas-job-id');
    }
    
    /**
     * 获取任务列表
     * @param string $vaultId
     * @return array
     */
    public function jobList( $vaultId ) {
        $request = new HttpRequest("/vaults/{$vaultId}/jobs", 'GET');
        $response = $this->executeRequest($request);
        
        $jobs = $response['JobList'];
        foreach ( $jobs as $index => $job ) {
            $jobs[$index]['CompletionDate'] = strtotime($job['CompletionDate']);
            $jobs[$index]['CreationDate'] = strtotime($job['CreationDate']);
        }
        return $jobs;
    }
    
    /**
     * 获取任务信息
     * @param string $vaultId
     * @param string $jobId
     * @return array
     */
    public function jobGetInfo($vaultId, $jobId) {
        $request = new HttpRequest("/vaults/{$vaultId}/jobs/{$jobId}", 'GET');
        $response = $this->executeRequest($request);
        $response['CompletionDate'] = strtotime($response['CompletionDate']);
        $response['CreationDate'] = strtotime($response['CreationDate']);
        return $response;
    }
    
    /**
     * 获取任务结果
     * @param string $vaultId
     * @param string $jobId
     * @return array
     */
    public function jobGetResult($vaultId, $jobId) {
        $request = new HttpRequest("/vaults/{$vaultId}/jobs/{$jobId}/output", 'GET');
        $response = $this->executeRequest($request);
        return $response;
    }
    
    /**
     * 启动数据取回任务 
     * @todo 待测试
     * @param string $vaultId
     * @param string $archiveId
     * @param array $range array('start', 'end')
     */
    public function jobStartArchiveRetrieval($vaultId, $archiveId, $range=null){
        $request = new HttpRequest("/vaults/{$vaultId}/jobs", 'POST');
        $request->setParam('Type', 'archive-retrieval');
        $request->setParam('ArchiveId', $vaultId);
        $request->setParam('Description', "Download {$archiveId}");
        if ( null !== $range ) {
            $request->setParam('RetrievalByteRange', "{$range[0]}-{$range[1]}");
        }
        $this->executeRequest($request);
        return $request->responseHeader('x-oas-job-id');
    }
    
    /**
     * 启动将OSS数据推送至OAS的任务
     * @todo 待测试
     * @param string $vaultId
     * @param string $host
     * @param string $bucket
     * @param string $object
     */
    public function jobStartPullFromOss($vaultId, $host, $bucket, $object) {
        $request = new HttpRequest("/vaults/{$vaultId}/jobs", 'POST');
        $request->setParam('Type', 'pull-from-oss');
        $request->setParam('OSSHost', $host);
        $request->setParam('Bucket', $bucket);
        $request->setParam('Object', $object);
        $request->setParam('Description', "Pull From OSS {$host}");
        $this->executeRequest($request);
        return $request->responseHeader('x-oas-job-id');
    }
    
    /**
     * 启动将OAS数据推送至OSS的任务
     * @todo 待测试
     * @param string $vaultId
     * @param string $archiveId
     * @param string $host
     * @param string $bucket
     * @param string $object
     */
    public function jobStartPushToOss($vaultId,$archiveId, $host, $bucket, $object){
        $request = new HttpRequest("/vaults/{$vaultId}/jobs", 'POST');
        $request->setParam('Type', 'push-to-oss');
        $request->setParam('OSSHost', $host);
        $request->setParam('Bucket', $bucket);
        $request->setParam('Object', $object);
        $request->setParam('ArchiveId', $vaultId);
        $request->setParam('Description', "Push to OSS {$host}");
        $this->executeRequest($request);
        return $request->responseHeader('x-oas-job-id');
    }
    
    # 这些接口暂时不实现， 准备写到archiveUpload中， 当文件大小到达指定大小时，
    # 自动启用分块上传，同事允许手动启动分块上传。 该组方法将会被移动到MultipartUploadManager中.
    # public function multipartUploadStart() {}
    # public function multipartUploadDelete() {}
    # public function multipartUploadPartUpload() {}
    # public function multipartUploadPartList() {}
    # public function multipartUploadPartMerge() {}
    
    /**
     * 执行请求
     * @param HttpRequest $request
     * @throws \Exception
     * @return array
     */
    private function executeRequest( HttpRequest $request ) {
        $request->headerSet('Host', $this->host);
        $this->setupAuthorization($request);
        $request->setUrl("https://{$this->host}{$request->getBaseUrl()}");
        $request->execute();
        $response = $request->responseJson();
        if ( isset($response['code'] )) {
            throw new \Exception($response['message']);
        }
        return $response;
    }
    
    /**
     * 用户签名计算
     * @see https://help.aliyun.com/document_detail/27386.html?spm=5176.doc27385.6.558.sOYMPv
     * @param HttpRequest $request
     */
    private function setupAuthorization( HttpRequest $request ) {
        # CanonicalizedResource
        $resource = $request->getUrl();
        if ( 'GET' === $request->getMethod() ) {
            $params = $request->getParams();
            if ( !empty($params) ) {
                $connector = (false===strpos($resource, '?')) ? '?' : '&';
                $resource = $resource.$connector.http_build_query($params);
            }
        }
        
        # CanonicalizedOASHeaders
        $headers = $request->headerToArray();
        foreach ( $headers as $hkey => $hvalue ) {
            if ( 'x-oas-' === substr($hkey, 0, 6) ) {
                $headers[$hkey] = str_replace(' ', '', strtolower($hkey).': '.$hvalue);
            } else {
                unset($headers[$hkey]);
            }
        }
        if ( !empty($headers) ) {
            sort($headers);
            $headers = trim(implode("\n", $headers))."\n";
        } else {
            $headers = "";
        }
        
        $date = gmdate("D, j M Y H:i:s").' GMT';
        $signatureData = sprintf("%s\n%s\n%s%s",$request->getMethod(),$date,$headers,$resource);
        $signature = base64_encode(hash_hmac("sha1", trim($signatureData), $this->accessKey, true));
        $auth = "OAS {$this->accessId}:{$signature}";
        
        $request->headerSet('Authorization', $auth);
        $request->headerSet('Date', $date);
    }
    
    /**
     * 获取文件的Tree Hash值
     * @param string $filepath
     * @return string
     */
    private function getFileTreeHash( $filepath ) {
        $file = fopen($filepath, "r");
        $md5s = array();
        do {
            $md5s[] = strtoupper(md5(fread($file, 1024*1024)));
        } while (!feof($file));
        fclose($file);
        
        while ( 1 < count($md5s) ) {
            $md5s = array_values($md5s);
            $count = count($md5s);
            $extraMd5 = (0===$count%2) ? null : array_pop($md5s);
            
            for ( $i=0; $i<$count; $i++ ) {
                $md5s[$i] = strtoupper(md5($md5s[$i].$md5s[$i+1]));
                unset($md5s[$i+1]);
                $i++;
            }
            if ( null !== $extraMd5 ) {
                $md5s[] = $extraMd5;
            }
        }
        return array_pop($md5s);
    }
}