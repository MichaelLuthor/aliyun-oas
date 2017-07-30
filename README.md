# aliyun-oas
阿里云的归档存储服务的PHP版本SDK，包含一个能够简单管理归档的命令行工具。

## example
$oas = new \Sige\Lib\AliyunOAS\AliyunOAS($accessId, $accessKey, $host);
$oas->vaultList();