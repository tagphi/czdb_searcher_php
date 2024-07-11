<?php

namespace Czdb\Entity;

class IndexBlock {
    private $startIp;
    private $endIp;
    private $dataPtr;
    private $dataLen;
    private $dbType;

    public function __construct($startIp, $endIp, $dataPtr, $dataLen, $dbType) {
        $this->startIp = $startIp;
        $this->endIp = $endIp;
        $this->dataPtr = $dataPtr;
        $this->dataLen = $dataLen;
        $this->dbType = $dbType;
    }

    public function getStartIp() {
        return $this->startIp;
    }

    public function setStartIp($startIp) {
        $this->startIp = $startIp;
        return $this;
    }

    public function getEndIp() {
        return $this->endIp;
    }

    public function setEndIp($endIp) {
        $this->endIp = $endIp;
        return $this;
    }

    public function getDataPtr() {
        return $this->dataPtr;
    }

    public function setDataPtr($dataPtr) {
        $this->dataPtr = $dataPtr;
        return $this;
    }

    public function getDataLen() {
        return $this->dataLen;
    }

    public function setDataLen($dataLen) {
        $this->dataLen = $dataLen;
        return $this;
    }

    public static function getIndexBlockLength($dbType) {
        return $dbType == 4 ? 13 : 37;
    }

    public function getBytes() {
        $ipBytesLength = $this->dbType == 'IPV4' ? 4 : 16;
        $b = array_fill(0, self::getIndexBlockLength($this->dbType), 0);

        for ($i = 0; $i < $ipBytesLength; $i++) {
            $b[$i] = ord($this->startIp[$i]);
            $b[$i + $ipBytesLength] = ord($this->endIp[$i]);
        }

        $this->writeIntLong($b, $ipBytesLength * 2, $this->dataPtr);
        $this->write($b, $ipBytesLength * 2 + 4, $this->dataLen, 1);

        return $b;
    }

    private function writeIntLong(&$b, $offset, $value) {
        $b[$offset] = ($value >> 24) & 0xFF;
        $b[$offset + 1] = ($value >> 16) & 0xFF;
        $b[$offset + 2] = ($value >> 8) & 0xFF;
        $b[$offset + 3] = $value & 0xFF;
    }

    private function write(&$b, $offset, $value, $length) {
        for ($i = 0; $i < $length; $i++) {
            $b[$offset + $i] = ($value >> (8 * ($length - $i - 1))) & 0xFF;
        }
    }
}
