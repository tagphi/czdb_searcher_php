<?php

namespace net\cz88\czdb\entity;

use Exception;

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
            return "null";
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
        $regionUnpacker = new MessagePackUnpacker();
        $regionUnpacker->reset($this->region);
        $geoPosMixSize = $regionUnpacker->unpackLong();
        $otherData = $regionUnpacker->unpackString();

        if ($geoPosMixSize == 0) {
            return $otherData;
        }

        $dataLen = ($geoPosMixSize >> 24) & 0xFF;
        $dataPtr = $geoPosMixSize & 0x00FFFFFF;

        $regionData = substr($geoMapData, $dataPtr, $dataLen);
        $sb = "";

        $geoColumnUnpacker = new MessagePackUnpacker();
        $geoColumnUnpacker->reset($regionData);
        $columnNumber = $geoColumnUnpacker->unpackArrayHeader();

        for ($i = 0; $i < $columnNumber; $i++) {
            $columnSelected = ($columnSelection >> ($i + 1) & 1) == 1;
            $value = $geoColumnUnpacker->unpackString();
            $value = ($value === "") ? "null" : $value;

            if ($columnSelected) {
                $sb .= $value . "\t";
            }
        }

        return $sb . $otherData;
    }
}
