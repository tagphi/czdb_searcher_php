# CZDB Searcher

CZDB Searcher 是一个用于高效 IP 地理位置查询的 PHP 库，它使用紧凑的数据库格式和二叉树搜索算法，提供快速准确的 IP 查找功能。

## 特点

- 快速的 IP 地理位置查询
- 支持 IPv4 和 IPv6 地址
- 简单易用的 API
- 为速度和大小优化的自定义数据库格式

## 安装

在项目目录下运行以下命令来安装 CZDB Searcher：

```bash
composer require czdb/czdb-searcher
```

## 使用方法

以下是一个快速开始的示例：

```php
<?php
require_once __DIR__ . '/vendor/autoload.php';

use Czdb\DbSearcher;

$dbSearcher = new DbSearcher("/path/to/your/database.czdb", "BTREE", "YourEncryptionKey");

$ip = "8.8.8.8";
$region = $dbSearcher->search($ip);

echo "搜索结果：\n";
print_r($region);
```

请将 `"/path/to/your/database.czdb"` 和 `"YourEncryptionKey"` 替换为您项目中实际的数据库路径和加密密钥。

## 配置

`DbSearcher` 构造函数接受以下参数：

- `databasePath`：您的 CZDB 数据库文件路径。
- `searchMode`：搜索模式（例如，"BTREE" 或者 "MEMORY"）。
- `encryptionKey`：密钥。

数据库文件和密钥可以从 [www.cz88.net](https://cz88.net/geo-public) 获取。

## 线程安全

请注意，只有 MEMORY 查询模式是线程安全的。如果你在高并发环境下使用 BTREE 查询模式，可能会导致打开的文件过多的错误。在这种情况下，你可以增加内核中允许打开的最大文件数（fs.file-max），或者使用 MEMORY 查询模式。当然更合理的一个方式是为线程池中的每一个线程只创建一个DbSearcher实例。

## 贡献

欢迎贡献！请随时提交拉取请求或开启问题来改进库或添加新功能。

## 许可证

该项目在 Apache-2.0 许可下授权 - 详情见 LICENSE 文件。