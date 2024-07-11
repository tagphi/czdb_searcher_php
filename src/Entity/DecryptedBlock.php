<?php

namespace Czdb\Entity;

use Czdb\Utils\ByteUtil;

class DecryptedBlock {
    private $clientId;
    private $expirationDate;
    private $randomSize;

    /**
     * Gets the client ID.
     * @return int The client ID.
     */
    public function getClientId() {
        return $this->clientId;
    }

    /**
     * Sets the client ID.
     * @param int $clientId The client ID to set.
     */
    public function setClientId($clientId) {
        $this->clientId = $clientId;
    }

    /**
     * Gets the expiration date.
     * @return int The expiration date.
     */
    public function getExpirationDate() {
        return $this->expirationDate;
    }

    /**
     * Sets the expiration date.
     * @param int $expirationDate The expiration date to set.
     */
    public function setExpirationDate($expirationDate) {
        $this->expirationDate = $expirationDate;
    }

    /**
     * Gets the size of the random bytes.
     * @return int The size of the random bytes.
     */
    public function getRandomSize() {
        return $this->randomSize;
    }

    /**
     * Sets the size of the random bytes.
     * @param int $randomSize The size of the random bytes to set.
     */
    public function setRandomSize($randomSize) {
        $this->randomSize = $randomSize;
    }

    /**
     * Decrypts the provided encrypted byte array using AES encryption with a specified key.
     * @param string $key The base64 encoded string representing the AES key.
     * @param string $encryptedBytes The encrypted byte array.
     * @return DecryptedBlock The decrypted block instance.
     * @throws Exception If an error occurs during decryption.
     */
    public static function decrypt($key, $encryptedBytes) {
        $keyBytes = base64_decode($key);
        $cipher = 'AES-128-ECB';
        $decryptedBytes = openssl_decrypt($encryptedBytes, $cipher, $keyBytes, OPENSSL_RAW_DATA);
        $decryptedBytes = array_values(unpack('C*', $decryptedBytes));

        $decryptedBlock = new DecryptedBlock();
        $decryptedBlock->setClientId(ByteUtil::getIntLong($decryptedBytes, 0) >> 20);
        $decryptedBlock->setExpirationDate(ByteUtil::getIntLong($decryptedBytes, 0) & 0xFFFFF);
        $decryptedBlock->setRandomSize(ByteUtil::getIntLong($decryptedBytes, 4));
        return $decryptedBlock;
    }
}
