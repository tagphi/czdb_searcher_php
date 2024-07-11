<?php

namespace Czdb\Entity;

class HeaderBlock {
    private $indexStartIp;
    private $indexPtr;

    public function __construct($indexStartIp, $indexPtr) {
        $this->indexStartIp = $indexStartIp;
        $this->indexPtr = $indexPtr;
    }

    public function getIndexStartIp() {
        return $this->indexStartIp;
    }

    public function setIndexStartIp($indexStartIp) {
        $this->indexStartIp = $indexStartIp;
        return $this;
    }

    public function getIndexPtr() {
        return $this->indexPtr;
    }

    public function setIndexPtr($indexPtr) {
        $this->indexPtr = $indexPtr;
        return $this;
    }

    public function getBytes() {
        $b = array_fill(0, 20, 0);
        foreach ($this->indexStartIp as $key => $value) {
            if ($key < 16) {
                $b[$key] = $value;
            }
        }
        $b[16] = ($this->indexPtr >> 24) & 0xFF;
        $b[17] = ($this->indexPtr >> 16) & 0xFF;
        $b[18] = ($this->indexPtr >> 8) & 0xFF;
        $b[19] = $this->indexPtr & 0xFF;
        return $b;
    }
}
