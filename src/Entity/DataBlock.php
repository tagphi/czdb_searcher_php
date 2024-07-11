<?php

namespace Czdb\Entity;

use Exception;
use MessagePack\BufferUnpacker;

class DataBlock {
    private $region;
    private $dataPtr;

    public function __construct($region, $dataPtr) {
        $this->region = $region;
        $this->dataPtr = $dataPtr;
    }

    public function getRegion($geoMapData, $columnSelection) {
        try {
            return $this->unpack($geoMapData, $columnSelection);
        } catch (Exception $e) {
            return null;
        }
    }

    public function setRegion($region) {
        $this->region = $region;
        return $this;
    }

    public function getDataPtr() {
        return $this->dataPtr;
    }

    public function setDataPtr($dataPtr) {
        $this->dataPtr = $dataPtr;
        return $this;
    }

    private function unpack($geoMapData, $columnSelection) {
        // Assuming MessagePack for PHP is installed and autoloaded
        $unpacker = new BufferUnpacker();
        $unpacker->reset($this->region);
        $geoPosMixSize = $unpacker->unpackInt();
        $otherData = $unpacker->unpackStr();

        if ($geoPosMixSize == 0) {
            return $otherData;
        }

        $dataLen = ($geoPosMixSize >> 24) & 0xFF;
        $dataPtr = $geoPosMixSize & 0x00FFFFFF;

        $regionData = substr($geoMapData, $dataPtr, $dataLen);
        $sb = "";

        $unpacker->reset($regionData);
        $columnNumber = $unpacker->unpackArrayHeader();

        for ($i = 0; $i < $columnNumber; $i++) {
            $columnSelected = ($columnSelection >> ($i + 1) & 1) == 1;
            $value = $unpacker->unpackStr();
            $value = ($value === "") ? "null" : $value;

            if ($columnSelected) {
                $sb .= $value . "\t";
            }
        }

        return $sb . $otherData;
    }
}
