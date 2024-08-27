<?php

class SymmetricEncryption {

    private $cipher;

    public function __construct($cipher = 'aes-256-cbc') {
        $this->cipher = $cipher;
    }

    private function getKeySize() {
        if (preg_match("/([0-9]+)/i", $this->cipher, $matches)) {
            return $matches[1] >> 3;
        }
        return 0;
    }

    private function derived($password, $salt) {

        $AESKeyLength = $this->getKeySize();
        $AESIVLength = openssl_cipher_iv_length($this->cipher);

        $pbkdf2 = hash_pbkdf2("SHA1", $password, mb_convert_encoding($salt, 'UTF-16LE'), 1000, $AESKeyLength + $AESIVLength, TRUE);

        $key = substr($pbkdf2, 0, $AESKeyLength);
        $iv =  substr($pbkdf2, $AESKeyLength, $AESIVLength);

        $derived = new stdClass();
        $derived->key = $key;
        $derived->iv = $iv;
        return $derived;
    }

    function encrypt($message, $password, $salt) {
        $derived = $this->derived($password, $salt);
        $enc = openssl_encrypt(mb_convert_encoding($message, 'UTF-16', 'UTF-8'), $this->cipher, $derived->key, 0, $derived->iv);
        return '$$'.$enc.'$$';
    }

    function decrypt($message, $password, $salt) {
        $derived = $this->derived($password, $salt);
        $dec = openssl_decrypt($message, $this->cipher, $derived->key, 0, $derived->iv);
        return mb_convert_encoding($dec, 'UTF-8', 'UTF-16');
    }

}