<?php

namespace Czdb\Utils;

use Exception;
use Czdb\Entity\DecryptedBlock;
use Czdb\Entity\HyperHeaderBlock;

class HyperHeaderDecoder {

    /**
     * @throws Exception
     */
    public static function decrypt($is, $key) {
        // Assuming $is is a file resource or binary string
        $headerBytes = fread($is, HyperHeaderBlock::HEADER_SIZE);

        $version = unpack('L', $headerBytes, 0)[1];
        $clientId = unpack('L', $headerBytes, 4)[1];
        $encryptedBlockSize = unpack('L', $headerBytes, 8)[1];

        $encryptedBytes = fread($is, $encryptedBlockSize);

        $decryptedBlock = DecryptedBlock::decrypt($key, $encryptedBytes);

        // Check if the clientId in the DecryptedBlock matches the clientId in the HyperHeaderBlock
        if ($decryptedBlock->getClientId() != $clientId) {
            throw new Exception("Wrong clientId");
        }

        // Check if the expirationDate in the DecryptedBlock is less than the current date
        $currentDate = intval(date("ymd"));
        if ($decryptedBlock->getExpirationDate() < $currentDate) {
            throw new Exception("DB is expired");
        }

        $hyperHeaderBlock = new HyperHeaderBlock();
        $hyperHeaderBlock->setVersion($version);
        $hyperHeaderBlock->setClientId($clientId);
        $hyperHeaderBlock->setEncryptedBlockSize($encryptedBlockSize);
        $hyperHeaderBlock->setDecryptedBlock($decryptedBlock);

        return $hyperHeaderBlock;
    }
}
