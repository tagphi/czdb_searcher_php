<?php

namespace net\cz88\czdb;

require './entity/HyperHeaderBlock.php';
require './entity/IndexBlock.php';
require './utils/HyperHeaderDecoder.php';
require './utils/Decryptor.php';

use Exception;
use net\cz88\czdb\utils\ByteUtil;
use net\cz88\czdb\utils\HyperHeaderDecoder;
use net\cz88\czdb\utils\Decryptor;
use net\cz88\czdb\entity\IndexBlock;

class DbSearcher {
    const SUPER_PART_LENGTH = 17;
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
     * @throws Exception
     */
    public function __construct($dbFile, $queryType, $key) {
        $this->queryType = $queryType;
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

    function compareBytes($bytes1, $bytes2, $length) {
        for ($i = 0; $i < min(strlen($bytes1), strlen($bytes2), $length); $i++) {
            if (($bytes1[$i] * $bytes2[$i]) > 0) {
                if ($bytes1[$i] < $bytes2[$i]) {
                    return -1;
                } elseif ($bytes1[$i] > $bytes2[$i]) {
                    return 1;
                }
            } elseif (($bytes1[$i] * $bytes2[$i]) < 0) {
                // When the signs are different, the negative byte is considered larger
                if ($bytes1[$i] > 0) {
                    return -1;
                } else {
                    return 1;
                }
            } elseif (($bytes1[$i] * $bytes2[$i]) == 0 && ($bytes1[$i] + $bytes2[$i]) != 0) {
                // When one byte is zero and the other is not, the zero byte is considered smaller
                if ($bytes1[$i] == 0) {
                    return -1;
                } else {
                    return 1;
                }
            }
        }
        if (strlen($bytes1) >= $length && strlen($bytes2) >= $length) {
            return 0;
        } else {
            return (strlen($bytes1) < strlen($bytes2)) ? -1 : 1;
        }
    }

    function getIpBytes($ip, $dbType) {
        if ($dbType == "IPV4") {
            // For IPv4, use filter_var to validate and inet_pton to convert
            if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                throw new Exception("IP [$ip] format error for $dbType");
            }
            $ipBytes = inet_pton($ip);
        } else {
            // For IPv6, also use filter_var to validate and inet_pton to convert
            if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                throw new Exception("IP [$ip] format error for $dbType");
            }
            $ipBytes = inet_pton($ip);
        }
        return $ipBytes;
    }

    function searchInHeader($ip, $headerLength, $HeaderSip, $HeaderPtr, $dbType) {
        $l = 0;
        $h = $headerLength - 1;
        $sptr = 0;
        $eptr = 0;

        while ($l <= $h) {
            $m = intval(($l + $h) / 2);
            $cmp = $this->compareBytes($ip, $HeaderSip[$m], $dbType);

            if ($cmp < 0) {
                $h = $m - 1;
            } elseif ($cmp > 0) {
                $l = $m + 1;
            } else {
                $sptr = $HeaderPtr[$m > 0 ? $m - 1 : $m];
                $eptr = $HeaderPtr[$m];
                break;
            }
        }

        // less than header range
        if ($l == 0) {
            return [0, 0];
        }

        if ($l > $h) {
            if ($l < $headerLength) {
                $sptr = $HeaderPtr[$l - 1];
                $eptr = $HeaderPtr[$l];
            } elseif ($h >= 0 && $h + 1 < $headerLength) {
                $sptr = $HeaderPtr[$h];
                $eptr = $HeaderPtr[$h + 1];
            } else { // search to last header line, possible in last index block
                $sptr = $HeaderPtr[$headerLength - 1];
                $blockLen = getIndexBlockLength($dbType);

                $eptr = $sptr + $blockLen;
            }
        }

        return [$sptr, $eptr];
    }

    public function loadGeoSetting($key) {
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
        $this->geoMapData = array_values(unpack('C*', fread($this->raf, $geoMapSize)));

        $decryptor = new Decryptor($key);
        $this->geoMapData = $decryptor->decrypt($this->geoMapData);
    }

    private function initBtreeModeParam() {
        $this->fseek( $this->raf, 0);
        $data = fread($this->raf, self::SUPER_PART_LENGTH);
        $this->totalHeaderBlockSize = unpack('L', $data, self::HEADER_BLOCK_PTR)[1];

        $data = fread($this->raf, $this->totalHeaderBlockSize);

        $this->initHeaderBlock($data, $this->totalHeaderBlockSize);
    }

    private function initHeaderBlock($headerBytes, $size) {
        $indexLength = 20;

        $len = $size / $indexLength;
        $idx = 0;

        for ($i = 0; $i < $len; $i += $indexLength) {
            $dataPtrSegment = substr($headerBytes, $i + 16, 4);
            $dataPtr = unpack('L', $dataPtrSegment, 0)[1];

            if ($dataPtr === 0) {
                break;
            }

            $this->HeaderSip[$idx] = substr($headerBytes, $i, 16);
            $this->HeaderPtr[$idx] = $dataPtr;
            $idx++;
        }

        $this->headerLength = $idx;
    }

    private function fseek($handler, $offset) {
        fseek($handler, $this->headerSize + $offset);
    }
}
