<?php

namespace Czdb\Entity;

use Czdb\Utils\ByteUtil;

class HyperHeaderBlock {
    const HEADER_SIZE = 12;

    protected $version;
    protected $clientId;
    protected $encryptedBlockSize;
    protected $encryptedData;
    protected $decryptedBlock;

    public function __construct() {
        // Initialize properties if necessary
    }

    public function getVersion() {
        return $this->version;
    }

    public function setVersion($version) {
        $this->version = $version;
    }

    public function getClientId() {
        return $this->clientId;
    }

    public function setClientId($clientId) {
        $this->clientId = $clientId;
    }

    public function getEncryptedBlockSize() {
        return $this->encryptedBlockSize;
    }

    public function setEncryptedBlockSize($encryptedBlockSize) {
        $this->encryptedBlockSize = $encryptedBlockSize;
    }

    public function getEncryptedData() {
        return $this->encryptedData;
    }

    public function setEncryptedData($encryptedData) {
        $this->encryptedData = $encryptedData;
    }

    public function getDecryptedBlock() {
        return $this->decryptedBlock;
    }

    public function setDecryptedBlock($decryptedBlock) {
        $this->decryptedBlock = $decryptedBlock;
    }

    public static function fromBytes($bytes) {
        $version = ByteUtil::getIntLong($bytes, 0);
        $clientId = ByteUtil::getIntLong($bytes, 4);
        $encryptedBlockSize = ByteUtil::getIntLong($bytes, 8);

        $headerBlock = new HyperHeaderBlock();
        $headerBlock->setVersion($version);
        $headerBlock->setClientId($clientId);
        $headerBlock->setEncryptedBlockSize($encryptedBlockSize);

        return $headerBlock;
    }

    public function getHeaderSize() {
        return 12 + $this->encryptedBlockSize + $this->decryptedBlock->getRandomSize();
    }
}