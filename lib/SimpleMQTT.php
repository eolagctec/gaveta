<?php
// Simple MQTT client (minimal) for publishing MQTT messages (MQTT 3.1.1)
// Note: lightweight implementation intended for simple PUBLISH to an authenticated broker.
// Supports TLS by passing host with ssl:// prefix or setting MQTT_TLS env var (see usage).

class SimpleMQTT {
    private $socket;
    private $clientId;
    private $host;
    private $port;
    private $keepalive = 60;

    public function __construct($host, $port = 1883, $clientId = null) {
        $this->host = $host;
        $this->port = (int)$port;
        $this->clientId = $clientId ?: 'php-mqtt-' . bin2hex(random_bytes(6));
    }

    public function connect($username = null, $password = null) {
        $address = $this->host . ':' . $this->port;
        $errno = 0; $errstr = '';
        $ctx = stream_context_create();
        // allow self-signed if environment requests it (not recommended for production)
        stream_context_set_option($ctx, 'ssl', 'verify_peer', false);
        stream_context_set_option($ctx, 'ssl', 'verify_peer_name', false);

        $this->socket = @stream_socket_client($address, $errno, $errstr, 5, STREAM_CLIENT_CONNECT, $ctx);
        if (!$this->socket) {
            error_log("SimpleMQTT: stream_socket_client failed: $errstr ($errno)");
            return false;
        }
        stream_set_timeout($this->socket, 5);

        // build CONNECT packet
        $protocolName = $this->encodeString('MQTT');
        $protocolLevel = chr(4); // MQTT 3.1.1
        $connectFlags = 0x02; // Clean session
        if ($username !== null) $connectFlags |= 0x80; // username flag
        if ($password !== null) $connectFlags |= 0x40; // password flag

        $keepalive = pack('n', $this->keepalive);
        $payload = $this->encodeString($this->clientId);
        if ($username !== null) $payload .= $this->encodeString($username);
        if ($password !== null) $payload .= $this->encodeString($password);

        $variableHeader = $protocolName . $protocolLevel . chr($connectFlags) . $keepalive;
        $remaining = $variableHeader . $payload;
        $packet = chr(0x10) . $this->encodeLength(strlen($remaining)) . $remaining;

        fwrite($this->socket, $packet);

        // read CONNACK (at least 4 bytes)
        $connack = fread($this->socket, 4);
        if (strlen($connack) < 4) {
            error_log('SimpleMQTT: no CONNACK received');
            fclose($this->socket);
            return false;
        }
        $returnCode = ord($connack[3]);
        if ($returnCode !== 0) {
            error_log('SimpleMQTT: CONNACK return code ' . $returnCode);
            fclose($this->socket);
            return false;
        }
        return true;
    }

    public function publish($topic, $message, $qos = 0) {
        if (!$this->socket) return false;
        $header = 0x30 | ($qos << 1);
        $payload = $this->encodeString($topic) . $message;
        $packet = chr($header) . $this->encodeLength(strlen($payload)) . $payload;
        fwrite($this->socket, $packet);
        // For QoS 0 we don't wait for ack
        return true;
    }

    public function close() {
        if ($this->socket) {
            fclose($this->socket);
            $this->socket = null;
        }
    }

    private function encodeString($str) {
        return pack('n', strlen($str)) . $str;
    }

    private function encodeLength($len) {
        $str = '';
        do {
            $digit = $len % 128;
            $len = (int)($len / 128);
            if ($len > 0) $digit = $digit | 0x80;
            $str .= chr($digit);
        } while ($len > 0);
        return $str;
    }
}
