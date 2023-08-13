<?php
/*
 *  EOPHP2 - A modular bot for EO
 *  Copyright (C) 2017  bloski
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */
class PacketProcessor {
    private $d_multi;
    private $e_multi;
    private $seq;
    public $seq_counter;
	public $sequence_target;
	private $challenge = -1;
	private $server_encval = -1;
	private $client_encval = -1;
	private $is_client;
	public $decrypt_key = -1;

    private $encoded;

    function __construct($is_client) {
        $this->encoded = false;
		$this->is_client = $is_client;
		$this->sequence_target = 10;
    }

    public function set_encoding($seq1, $seq2) {
        $this->d_multi = $seq1;
        $this->e_multi = $seq2;
        //$this->set_seq($seq1 * 7 + $seq2 - 5);
		$this->set_seq(7 * $seq1 + $seq2 - 5);
        $this->seq_counter = 0;
        $this->bologna_counter = 0;
    }
	
	public function HasEncryption()
	{
		return $this->server_encval >= 0 && $this->client_encval >= 0;
	}

	public function RememberChallenge($challenge)
	{
		$this->challenge = $challenge;
	}

	public function SetupEncryptionFromInit($server_encval, $client_encval)
	{
		if ($this->challenge < 0)
			throw new Exception("Challenge was not set before SetupEncryptionFromInit");
		$this->server_encval = $server_encval;
		$this->client_encval = $client_encval += $this->challenge % 11;
		
		//echo "\n\n\n\n--". $this->server_encval . " ". $this->client_encval . "\n";
	}

	public function UpdateEncryptionFromClient($n)
	{
		$this->client_encval += $n;
	}
	
	public function SetIsClient($is_client)
	{
		$this->is_client = $is_client;
	}
	
	public function IsClient()
	{
		return $this->is_client;
	}
	
	public function GetServerEncVal()
	{
		return $this->server_encval;
	}
	
	public function GetClientEncVal()
	{
		return $this->client_encval;
	}
	
	public function UpdateEncryptionFromServer($n)
	{
		$this->server_encval += $n + $this->client_encval % 50;
	}

    public function set_seq($new_seq) {
        $this->seq = $new_seq;
    }
	
	public function get_seq() {
        return $this->seq;
    }

    public function next_sequence() {
        if (++$this->seq_counter >= $this->sequence_target) {
            $this->seq_counter = 0;
			if($this->sequence_target != 10){
			$this->seq -= 9;
			$this->seq += $this->sequence_target - 1;
			}
            $this->sequence_target = 10;
		}

        return $this->seq + $this->seq_counter;
    }

    public function s_process($b) {
        if (ord($b[0]) == Protocol::A['Init'] && ord($b[1]) == Protocol::F['Init'])
			return $b;

		if (!$this->HasEncryption())
			throw new Exception("Encryption parameters not set");

		$encval = $this->is_client ? $this->client_encval : $this->server_encval;

		$enckey_table = [
			fn ($i) => -($i + 0x74),
			fn ($i) => +floor($encval / 253),
			fn ($i) => -(($encval - 1) % 253),
		];

		// Intentionally ignores the last byte of the packet
		for ($i = 1; $i < strlen($b); ++$i)
		{
			$val = ord($b[$i - 1]);
			$val = ($val + $enckey_table[$i % 3]($i)) & 0xFF;
			$b[$i - 1] = chr($val);
		}

		return $b;
		/*
		$bytes = implode('', $ar);

        $packet = chr($type->family).chr($type->action);
        if ($type->action != Protocol::A['Init'] || $type->family != Protocol::F['Init']) {
            $this->encoded = true;
            $packet .= Protocol::EncodeInteger($this->next_sequence());
        }

        $packet .= $bytes;

        if ($type->action != Protocol::A['Init'] || $type->family != Protocol::F['Init']) {
            if (!isset($this->e_multi)) {
                throw new Exception('Tried to write encoded packet before setting encoding');
            }

            $packet = Protocol::Encode(Protocol::Dwind($packet, $this->e_multi));
        }

        $packet = Protocol::EncodeInteger(strlen($packet), 2).$packet;

        return $packet;*/
    }

    public function r_process($b) {
		if (ord($b[0]) == Protocol::A['Init'] && ord($b[1]) == Protocol::F['Init'])
		{
			return new Packet($b);
		}

		if (!$this->HasEncryption())
			throw new Exception("Encryption parameters not set");

		$decval = $this->is_client ? $this->client_encval : $this->server_encval;
		
		$deckey_table = [
			fn ($i) => +($i + 0x74),
			fn ($i) => -floor($decval / 253),
			fn ($i) => +(($decval - 1) % 253),
		];

		// Intentionally ignores the last byte of the packet
		for ($i = 1; $i < strlen($b); ++$i)
		{
			$val = ord($b[$i - 1]);
			$val = ($val + $deckey_table[$i % 3]($i)) & 0xFF;
			$b[$i - 1] = chr($val);
		}
		
		// $packet = new Packet(Protocol::Decode($b));
		$packet = new Packet($b);
		
		return $packet;
		/*
        if (strlen($bytes) < 3) {
            return null;
        }

        $action = ord($bytes[0]);
        $family = ord($bytes[1]);

        if ($action == Protocol::A['Init'] && $family == Protocol::F['Init']) {
            $this->encoded = false;
            return new Packet($bytes);
        } else {
            $this->encoded = true;
            if (!isset($this->d_multi)) {
                throw new Exception('Tried to read encoded packet before setting encoding');
            }

            $packet = new Packet(Protocol::Dwind(Protocol::Decode($bytes), $this->d_multi));

            return $packet;
        }
		*/
		
    }
}
