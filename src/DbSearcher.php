<?php
namespace Czdb;

use Exception;
use Czdb\Entity\DataBlock;
use Czdb\Entity\IndexBlock;
use Czdb\Utils\Decryptor;
use Czdb\Utils\HyperHeaderDecoder;

/**
 * DbSearcher 类用于数据库搜索，支持内存搜索和B树搜索。
 */
class DbSearcher {
    const SUPER_PART_LENGTH = 17;
    const FIRST_INDEX_PTR = 5;
    const END_INDEX_PTR = 13;
    const HEADER_BLOCK_PTR = 9;
    const FILE_SIZE_PTR = 1;

    const QUERY_TYPE_MEMORY = "MEMORY";
    const QUERY_TYPE_BTREE = "BTREE";

    private $dbType;
    private $ipBytesLength;
    private $queryType;
    private $totalHeaderBlockSize;
    private $raf;
    private $fileName;
    private $HeaderSip = [];
    private $HeaderPtr = [];
    private $headerLength;
    private $firstIndexPtr = 0;
    private $totalIndexBlocks = 0;
    private $dbBinStr = null;
    private $columnSelection = 0;
    private $geoMapData = null;
    private $headerSize = 0;

    /**
     * 构造函数，初始化数据库搜索器。
     *
     * @param string $dbFile 数据库文件路径。
     * @param string $queryType 查询类型，支持 MEMORY 和 BTREE。
     * @param string $key 解密密钥。
     * @throws Exception 如果文件打开失败或IP格式错误。
     */
    public function __construct($dbFile, $queryType, $key) {
        $this->queryType = $queryType;
        $this->fileName = $dbFile;
        $this->raf = fopen($dbFile, "rb");
        $headerBlock = HyperHeaderDecoder::decrypt($this->raf, $key);

        $offset = $headerBlock->getHeaderSize();
        $this->headerSize = $offset;

        fseek($this->raf, $offset);

        $superBytes = fread($this->raf, DbSearcher::SUPER_PART_LENGTH);
        $superBytes = array_values(unpack("C*", $superBytes));

        $this->dbType = ($superBytes[0] & 1) == 0 ? 4 : 6;
        $this->ipBytesLength = $this->dbType == 4 ? 4 : 16;

        $this->loadGeoSetting($key);

        if ($queryType == self::QUERY_TYPE_MEMORY) {
            $this->initializeForMemorySearch();
        } elseif ($queryType == self::QUERY_TYPE_BTREE) {
            $this->initBtreeModeParam();
        }
    }

    /**
     * 根据IP地址搜索数据块。
     *
     * @param string $ip IP地址。
     * @return string|null 返回找到的地理位置，如果没有找到返回 null。
     * @throws Exception 如果IP格式错误。
     */
    public function search($ip) {
        $ipBytes = $this->getIpBytes($ip);

        $dataBlock = null;

        if ($this->queryType == self::QUERY_TYPE_MEMORY) {
            $dataBlock = $this->memorySearch($ipBytes);
        } elseif ($this->queryType == self::QUERY_TYPE_BTREE) {
            $dataBlock = $this->bTreeSearch($ipBytes);
        }

        if ($dataBlock == null) {
            return null;
        } else {
            return $dataBlock->getRegion($this->geoMapData, $this->columnSelection);
        }
    }

    /**
     * 关闭数据库文件并释放资源。
     */
    public function close() {
        // Close file handle
        if (is_resource($this->raf)) {
            fclose($this->raf);
            $this->raf = null;
        }

        // Reset large data structures
        $this->dbBinStr = null;
        $this->HeaderSip = [];
        $this->HeaderPtr = [];
        $this->geoMapData = null;
    }

    /**
     * 比较两个字节序列。
     *
     * @param array $bytes1 第一个字节序列。
     * @param array $bytes2 第二个字节序列。
     * @param int $length 比较的长度。
     * @return int 返回比较结果：-1 表示 $bytes1 < $bytes2，1 表示 $bytes1 > $bytes2，0 表示相等。
     */
    private function compareBytes($bytes1, $bytes2, $length) {
        // unpack的数组下标从1开始
        for ($i = 1; $i <= $length; $i++) {
            $byte1 = $bytes1[$i];
            $byte2 = $bytes2[$i];

            if ($byte1 != $byte2) {
                // Compare based on byte values
                return $byte1 < $byte2 ? -1 : 1;
            }
        }
        // If all bytes are equal up to $length, return 0
        return 0;
    }

    /**
     * 内存搜索实现。
     *
     * @param array $ip IP地址的字节序列。
     * @return DataBlock|null 返回找到的数据块，如果没有找到返回 null。
     */
    private function memorySearch($ip) {
        $l = 0;
        $h = $this->totalIndexBlocks;

        $dataPtr = 0;
        $dataLen = 0;

        $blockLen = IndexBlock::getIndexBlockLength($this->dbType);

        while ($l <= $h) {
            $m = intval(($l + $h) / 2);
            $p = $this->firstIndexPtr + intval($m * $blockLen);
            $sip = unpack('C*', substr($this->dbBinStr, $p, $this->ipBytesLength));
            $eip = unpack('C*', substr($this->dbBinStr, $p + $this->ipBytesLength, $this->ipBytesLength));

            $cmpStart = $this->compareBytes($ip, $sip, $this->ipBytesLength);
            $cmpEnd = $this->compareBytes($ip, $eip, $this->ipBytesLength);

            if ($cmpStart >= 0 && $cmpEnd <= 0) {
                $dataPtr = unpack("L", substr($this->dbBinStr, $p + $this->ipBytesLength * 2, 4))[1];
                $dataLen = ord($this->dbBinStr[$p + $this->ipBytesLength * 2 + 4]);
                break;
            } elseif ($cmpStart < 0) {
                $h = $m - 1;
            } else {
                $l = $m + 1;
            }
        }

        if ($dataPtr == 0) {
            return null;
        }

        $region = substr($this->dbBinStr, $dataPtr, $dataLen);

        return new DataBlock($region, $dataPtr);
    }

    /**
     * B树搜索实现。
     *
     * @param array $ip IP地址的字节序列。
     * @return DataBlock|null 返回找到的数据块，如果没有找到返回 null。
     */
    private function bTreeSearch($ip) {
        $sptrNeptr = $this->searchInHeader($ip);

        $sptr = $sptrNeptr[0];
        $eptr = $sptrNeptr[1];

        if ($sptr == 0) {
            return null;
        }

        // Calculate block length and buffer length
        $blockLen = $eptr - $sptr;
        $blen = IndexBlock::getIndexBlockLength($this->dbType); // Assume getIndexBlockLength() is defined elsewhere

        // Read the index blocks into a buffer
        $this->fseek($this->raf, $sptr);
        $iBuffer = fread($this->raf, $blockLen + $blen);

        $l = 0;
        $h = $blockLen / $blen;

        $dataPtr = 0;
        $dataLen = 0;

        while ($l <= $h) {
            $m = intval(($l + $h) / 2);
            $p = $m * $blen;
            $sip = unpack('C*', substr($iBuffer, $p, $this->ipBytesLength));
            $eip = unpack('C*', substr($iBuffer, $p + $this->ipBytesLength, $this->ipBytesLength));

            $cmpStart = $this->compareBytes($ip, $sip, $this->ipBytesLength); // Assume compareBytes() is defined elsewhere
            $cmpEnd = $this->compareBytes($ip, $eip, $this->ipBytesLength); // Assume compareBytes() is defined elsewhere

            if ($cmpStart >= 0 && $cmpEnd <= 0) {
                // IP is within this block
                $dataPtr = unpack("L", substr($iBuffer, $p + $this->ipBytesLength * 2, 4))[1];
                $dataLen = ord($iBuffer[$p + $this->ipBytesLength * 2 + 4]);

                break;
            } elseif ($cmpStart < 0) {
                // IP is less than this block, search in the left half
                $h = $m - 1;
            } else {
                // IP is greater than this block, search in the right half
                $l = $m + 1;
            }
        }

        if ($dataPtr == 0) {
            return null;
        }

        // Retrieve the data
        $this->fseek($this->raf, $dataPtr);
        $region = fread($this->raf, $dataLen);

        return new DataBlock($region, $dataPtr); // Assume DataBlock class is defined elsewhere
    }

    /**
     * 将IP地址转换为字节序列。
     *
     * @param string $ip IP地址。
     * @return array 返回IP地址的字节序列。
     * @throws Exception 如果IP格式错误。
     */
    private function getIpBytes($ip) {
        if ($this->dbType == 4) {
            // For IPv4, use filter_var to validate and inet_pton to convert
            if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                throw new Exception("IP [$ip] format error for $this->dbType");
            }
            $ipBytes = inet_pton($ip);
        } else {
            // For IPv6, also use filter_var to validate and inet_pton to convert
            if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                throw new Exception("IP [$ip] format error for $this->dbType");
            }
            $ipBytes = inet_pton($ip);
        }
        return unpack('C*', $ipBytes);
    }

    /**
     * 在头部信息中搜索IP地址。
     *
     * @param array $ip IP地址的字节序列。
     * @return array 返回搜索结果，包含起始指针和结束指针。
     */
    private function searchInHeader($ip) {
        $l = 0;
        $h = $this->headerLength - 1;
        $sptr = 0;
        $eptr = 0;

        while ($l <= $h) {
            $m = intval(($l + $h) / 2);
            $cmp = $this->compareBytes($ip, $this->HeaderSip[$m], $this->ipBytesLength);

            if ($cmp < 0) {
                $h = $m - 1;
            } elseif ($cmp > 0) {
                $l = $m + 1;
            } else {
                $sptr = $this->HeaderPtr[$m > 0 ? $m - 1 : $m];
                $eptr = $this->HeaderPtr[$m];
                break;
            }
        }

        // less than header range
        if ($l == 0 && $h <=0) {
            return [0, 0];
        }

        if ($l > $h) {
            if ($l < $this->headerLength) {
                $sptr = $this->HeaderPtr[$l - 1];
                $eptr = $this->HeaderPtr[$l];
            } elseif ($h >= 0 && $h + 1 < $this->headerLength) {
                $sptr = $this->HeaderPtr[$h];
                $eptr = $this->HeaderPtr[$h + 1];
            } else { // search to last header line, possible in last index block
                $sptr = $this->HeaderPtr[$this->headerLength - 1];
                $blockLen = IndexBlock::getIndexBlockLength($this->dbType);

                $eptr = $sptr + $blockLen;
            }
        }

        return [$sptr, $eptr];
    }

    /**
     * 加载地理位置映射表。
     *
     * @param string $key 解密密钥。
     */
    private function loadGeoSetting($key) {
        $this->fseek($this->raf, self::END_INDEX_PTR);
        $data = fread($this->raf, 4);
        $endIndexPtr = unpack('L', $data, 0)[1];

        $columnSelectionPtr = $endIndexPtr + IndexBlock::getIndexBlockLength($this->dbType);
        $this->fseek($this->raf, $columnSelectionPtr);
        $data = fread($this->raf, 4);
        $this->columnSelection = unpack('L', $data, 0)[1];

        if ($this->columnSelection == 0) {
            return;
        }

        $geoMapPtr = $columnSelectionPtr + 4;
        $this->fseek($this->raf, $geoMapPtr);
        $data = fread($this->raf, 4);
        $geoMapSize = unpack('L', $data, 0)[1];

        $this->fseek($this->raf, $geoMapPtr + 4);
        $this->geoMapData = fread($this->raf, $geoMapSize);

        $decryptor = new Decryptor($key);
        $this->geoMapData = $decryptor->decrypt($this->geoMapData);
    }

    /**
     * 为内存搜索初始化参数。
     * @throws Exception 如果文件大小不匹配。
     */
    private function initializeForMemorySearch() {
        $this->fseek($this->raf, 0);
        $fileSize = filesize($this->fileName) - $this->headerSize;
        $this->dbBinStr = fread($this->raf, $fileSize);

        $this->totalHeaderBlockSize = unpack('L', $this->dbBinStr, self::HEADER_BLOCK_PTR)[1];

        $fileSizeInFile = unpack('L', $this->dbBinStr, self::FILE_SIZE_PTR)[1];

        if ($fileSize != $fileSizeInFile) {
            throw new Exception("FileSize not match with the file");
        }

        $this->firstIndexPtr = unpack('L', $this->dbBinStr, self::FIRST_INDEX_PTR)[1];
        $lastIndexPtr = unpack('L', $this->dbBinStr, self::END_INDEX_PTR)[1];
        $this->totalIndexBlocks = (int) (($lastIndexPtr - $this->firstIndexPtr) / IndexBlock::getIndexBlockLength($this->dbType)) + 1;

        $headerBlockBytes = substr($this->dbBinStr, self::SUPER_PART_LENGTH, $this->totalHeaderBlockSize);
        $this->initHeaderBlock($headerBlockBytes, $this->totalHeaderBlockSize);
    }

    /**
     * 为B树搜索模式初始化参数。
     */
    private function initBtreeModeParam() {
        $this->fseek( $this->raf, 0);
        $data = fread($this->raf, self::SUPER_PART_LENGTH);
        $this->totalHeaderBlockSize = unpack('L', $data, self::HEADER_BLOCK_PTR)[1];

        $data = fread($this->raf, $this->totalHeaderBlockSize);

        $this->initHeaderBlock($data, $this->totalHeaderBlockSize);
    }

    /**
     * 初始化头部块。
     *
     * @param string $headerBytes 头部块的字节序列。
     * @param int $size 头部块的大小。
     */
    private function initHeaderBlock($headerBytes, $size) {
        $indexLength = 20;

        $idx = 0;

        for ($i = 0; $i < $size; $i += $indexLength) {
            $dataPtrSegment = substr($headerBytes, $i + 16, 4);
            $dataPtr = unpack('L', $dataPtrSegment, 0)[1];

            if ($dataPtr === 0) {
                break;
            }

            $this->HeaderSip[$idx] = unpack('C*', substr($headerBytes, $i, 16));
            $this->HeaderPtr[$idx] = $dataPtr;
            $idx++;
        }

        $this->headerLength = $idx;
    }

    /**
     * 移动文件指针
     *
     * @param resource $handler 文件句柄。
     * @param int $offset 偏移量。
     */
    private function fseek($handler, $offset) {
        fseek($handler, $this->headerSize + $offset);
    }
}
