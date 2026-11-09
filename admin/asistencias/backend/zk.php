<?php

class ZK {

    private $ip;
    private $port = 4370;
    private $socket;

    public function __construct($ip) {
        $this->ip = $ip;
    }

    public function connect() {
        $this->socket = fsockopen($this->ip, $this->port, $errno, $errstr, 3);
        if (!$this->socket) {
            return false;
        }
        return true;
    }

    public function getAttendance() {
        fwrite($this->socket, "\x50\x50\x50\x50");
        $data = fread($this->socket, 8192);
        return $data;
    }

    public function disconnect() {
        fclose($this->socket);
    }
}
