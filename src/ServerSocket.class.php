<?php

class ServerSocket
{
    private $socket;
    private $buffer_size = 8192;
    private $data;
	private $eobot;

    function __construct($host, $port, &$eobot)
    {
		$this->eobot = $eobot;
        $this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if (!socket_connect($this->socket, $host, $port)) {
            Console::Log("Connect to server failed", "error");
            //exit(1);
        }
    }

    public function Receive()
    {
        $readSockets = [$this->socket];
        $writeSockets = [];
        $exceptSockets = [];

        // Wait for the socket to become readable or until the timeout is reached
        if (socket_select($readSockets, $writeSockets, $exceptSockets, 0) === false) {
            // Handle socket select error
        } elseif (in_array($this->socket, $readSockets)) {
            $this->data = socket_read($this->socket, $this->buffer_size);
            if ($this->data === false || strlen($this->data) === 0) {
                // Client/server disconnected or error occurred
                Console::Log("Server disconnected", "error");
				$this->eobot->should_exit = true;
                //exit(1);
            }
        } else {
            $this->data = null;
			//exit(1);
        }

        return $this->data;
    }

    public function Send($data)
    {
        socket_write($this->socket, $data, strlen($data));
    }

    public function Close()
    {
        socket_close($this->socket);
    }
}
