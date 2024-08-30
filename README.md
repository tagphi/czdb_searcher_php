# CZDB Searcher

CZDB Searcher 是一个用于高效 IP 地理位置查询的 PHP 库，它使用紧凑的数据库格式和二叉树搜索算法，提供快速准确的 IP 查找功能。

## 特点

- IP 地理位置查询
- 支持 IPv4 和 IPv6 地址
- 简单易用的 API

## 性能
因为php本生的特性，在每次请求解释执行完后会释放所有内存资源，这意味着每次请求都要重新载入数据文件，显然这会带来性能瓶颈。如果您对性能有比较高的要求，可以考虑java或者c版本的查询程序。

## 安装

在项目目录下运行以下命令来安装 CZDB Searcher：

```bash
composer require czdb/searcher
```

如果找不到包，可能是因为你没有使用composer 2.x版本，可以使用以下命令来安装composer 2.x版本：

```bash
composer self-update --2
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

$dbSearcher->close();
```

请将 `"/path/to/your/database.czdb"` 和 `"YourEncryptionKey"` 替换为您项目中实际的数据库路径和加密密钥。

## 配置

`DbSearcher` 构造函数接受以下参数：

- `databasePath`：您的 CZDB 数据库文件路径。
- `searchMode`：搜索模式（例如，"BTREE" 或者 "MEMORY"）。
- `encryptionKey`：密钥。

数据库文件和密钥可以从 [www.cz88.net](https://cz88.net/geo-public) 获取。

## 模式选择

- **批量查询**：对于批量查询，建议使用 "MEMORY" 模式。这是因为 "MEMORY" 模式会将数据库加载到内存中，从而提高查询速度，尤其是在处理大量查询时。虽然这会增加内存的使用，但可以显著提高批量处理的效率。  

- **少量查询**：如果每个请求只查询少量的 IP 地址，那么使用 "BTREE" 模式可能更合适。"BTREE" 模式不需要预先加载整个数据库到内存中，适用于处理较少量的查询请求，可以减少内存的使用，同时保持良好的查询性能。

## 贡献

欢迎贡献！请随时提交拉取请求或开启问题来改进库或添加新功能。

## 许可证

该项目在 Apache-2.0 许可下授权 - 详情见 LICENSE 文件。