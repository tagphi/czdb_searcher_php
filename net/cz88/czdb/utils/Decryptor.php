<?php


namespace net\cz88\czdb\utils;


class Decryptor {
    private $keyBytes;
    private $keyBytesLen;

    public function __construct($key) {
        $this->keyBytes = array_values(unpack("C*", base64_decode($key)));
        $this->keyBytesLen = count($this->keyBytes);
    }

    public function decrypt($data) {
        $result = [];

        for ($i = 0; $i < count($data); $i++) {
            $result[] = $data[$i] ^ $this->keyBytes[$i % $this->keyBytesLen];
        }
    }
}
