<?php
namespace Czdb\Utils;

class Decryptor {
    private $keyBytes;
    private $keyBytesLen;

    public function __construct($key) {
        $this->keyBytes = base64_decode($key);
        $this->keyBytesLen = strlen($this->keyBytes);
    }

    public function decrypt($data) {
        $result = '';

        for ($i = 0, $dataLen = strlen($data); $i < $dataLen; $i++) {
            // Perform XOR operation on the ASCII values of the characters
            $result .= $data[$i] ^ $this->keyBytes[$i % $this->keyBytesLen];
        }

        return $result;
    }
}
