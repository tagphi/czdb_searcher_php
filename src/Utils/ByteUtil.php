<?php

namespace Czdb\Utils;

class ByteUtil {
    /**
     * Writes specified bytes to a byte array starting from a given offset.
     *
     * @param array &$b The byte array to write to (passed by reference)
     * @param int $offset The position in the array to start writing
     * @param int $v The value to write
     * @param int $bytes The number of bytes to write
     */
    public static function write(array &$b, int $offset, int $v, int $bytes): void {
        for ($i = 0; $i < $bytes; $i++) {
            $b[$offset++] = chr(($v >> (8 * $i)) & 0xFF);
        }
    }

    /**
     * Writes an integer to a byte array.
     *
     * @param array &$b The byte array to write to (passed by reference)
     * @param int $offset The position in the array to start writing
     * @param int $v The value to write
     */
    public static function writeIntLong(array &$b, int $offset, int $v): void {
        $b[$offset++] = chr(($v >> 0) & 0xFF);
        $b[$offset++] = chr(($v >> 8) & 0xFF);
        $b[$offset++] = chr(($v >> 16) & 0xFF);
        $b[$offset] = chr(($v >> 24) & 0xFF);
    }

    /**
     * Gets an integer from a byte array starting from a specified offset.
     *
     * @param array $b The byte array to read from
     * @param int $offset The position in the array to start reading
     * @return int The integer value read from the byte array
     */
    public static function getIntLong(array $b, int $offset): int {
        return (
            ($b[$offset++] & 0xFF) |
            (($b[$offset++] << 8) & 0xFF00) |
            (($b[$offset++] << 16) & 0xFF0000) |
            (($b[$offset] << 24) & 0xFF000000)
        );
    }

    /**
     * Gets a 3-byte integer from a byte array starting from a specified offset.
     *
     * @param array $b The byte array to read from
     * @param int $offset The position in the array to start reading
     * @return int The integer value read from the byte array
     */
    public static function getInt3(array $b, int $offset): int {
        return (
            (ord($b[$offset++]) & 0xFF) |
            ((ord($b[$offset++]) & 0xFF) << 8) |
            ((ord($b[$offset]) & 0xFF) << 16)
        );
    }

    /**
     * Gets a 2-byte integer from a byte array starting from a specified offset.
     *
     * @param array $b The byte array to read from
     * @param int $offset The position in the array to start reading
     * @return int The integer value read from the byte array
     */
    public static function getInt2(array $b, int $offset): int {
        return (
            (ord($b[$offset++]) & 0xFF) |
            ((ord($b[$offset]) & 0xFF) << 8)
        );
    }

    /**
     * Gets a 1-byte integer from a byte array starting from a specified offset.
     *
     * @param array $b The byte array to read from
     * @param int $offset The position in the array to start reading
     * @return int The integer value read from the byte array
     */
    public static function getInt1(array $b, int $offset): int {
        return (ord($b[$offset]) & 0xFF);
    }
}
