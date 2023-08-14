<?php
require_once('src/Packet.class.php');
require_once('src/PacketType.class.php');
require_once('src/PacketProcessor.class.php');
require_once('src/Protocol.class.php');
require_once('src/EOStructures.php');
require_once('src/Console.class.php');
require_once('src/BotConfig.class.php');
require_once('src/ServerSocket.class.php');
require_once('src/CommandHandler.class.php');
require_once('src/PacketHandler.class.php');

class ChatMessage
{
	public $time;
	public $name;
	public $message;
}

class LocalMessage extends ChatMessage
{
	public $character_id;
}

class EOBot
{
	const STATE_UNINITIALIZED = 0;
    const STATE_INIT = 1;
    const STATE_LOGGED_IN = 2;
    const STATE_IN_GAME = 3;
    const STATE_DEAD = 4;

    const STATE_NAMES = array(
        0 => 'UNINITIALIZED',
        1 => 'INIT',
        2 => 'LOGGED_IN',
        3 => 'IN_GAME',
        4 => 'DEAD'
    );
	
	public $config;
	private $server_socket;
	private $packet_processor;
	private $packet_handler;
	private $last_pong;
	public $last_pong_reply;
	private $state;
	private $character;
	private $chat_log;
	private $nearby_characters;
	public $me;
	
	function __construct()
	{
		$this->config = new BotConfig();
		$this->last_pong = time();
		$this->last_pong_reply = time();
		$this->chat_log = array("Local" => array(), 
								"Global" => array(), 
								"Private" => array(), 
								"Party" => array(), 
								"Announce" => array(), 
								"System" => array());
		$nearby_characters = array();

		echo " 88888888b  .88888.   888888ba             dP   
 88        d8'   `8b  88    `8b            88   
a88aaaa    88     88 a88aaaa8P' .d8888b. d8888P 
 88        88     88  88   `8b. 88'  `88   88   
 88        Y8.   .8P  88    .88 88.  .88   88   
 88888888P  `8888P'   88888888P `88888P'   dP   
oooooooooooooooooooooooooooooooooooooooooooooooo\n";
		echo "Ver 0.0.1a                              By Maiku\n\n";
		Console::Log("Welcome to EOBot for ⚡EO Recharged⚡");
	}
	
	public function SetState($new_state){ $this->state = $new_state; }
	
	public function GetState(){ return $this->state; }
	
	public function GetPacketProcessor(){ return $this->packet_processor; }
	
	public function ReadCharacterData($packet)
	{
		$name = $packet->get_string();
		$id = $packet->get_int(2);
		$map_x = $packet->get_int(1);
		$map_y = $packet->get_int(1);
		
		$unk1 = $packet->get_int(2); // always 0?
		$unk2 = $packet->get_int(2); // always 0?
		$unk3 = $packet->get_int(2); // always 0?
		
		$state = $packet->get_int(1);
		$level = $packet->get_int(2);
		$unk4 = $packet->get_int(1); // ?
		$race_info = $packet->get_int(1);
		$hairstyle = $packet->get_int(1);
		$haircolor = $packet->get_bytes(1);
		
		$hp = $packet->get_int(3);
		$max_hp = $packet->get_int(3);
		$mp = $packet->get_int(3);
		$max_mp = $packet->get_int(3);
		$guild = $packet->get_bytes(3);
		
		// BAHWSBBT
		// boots armor helm wep shield buddy shoulder, buddy ground, torch
		$boots = $packet->get_int(2);
		$armor = $packet->get_int(2);
		$helm = $packet->get_int(2);
		$weapon = $packet->get_int(2);
		$shield = $packet->get_int(2);
		$buddy_s = $packet->get_int(2);
		$buddy_g = $packet->get_int(2);
		$torch = $packet->get_int(2);
		
		$packet->ignore(1); //255
		
		$character = new EOCharacter();
		$character->name = $name;
		$character->id = $id;
		$character->map_x = $map_x;
		$character->map_y = $map_y;
		$character->direction = $state & EOCharacter::DIRECTION_MASK;
		//TODO: also chair sitting
		$character->sitting = ($state & EOCharacter::SITTING_MASK) !== 0;
		$character->hidden = ($state & EOCharacter::HIDDEN_MASK) !== 0;
		$character->level = $level;
		$character->gender = ($race_info & EOCharacter::GENDER_MASK) !== 0;
		$character->skin = $race_info & EOCharacter::SKIN_MASK;
		$character->hairstyle = $hairstyle;
		$character->haircolor = $haircolor;
		$character->hp = $hp;
		$character->max_hp = $max_hp;
		$character->mp = $mp;
		$character->max_mp = $max_mp;
		$character->guild = $guild;
		
		//TODO: paperdoll

		return $character;
	}
	
	public function AddNearbyCharacter($character)
	{
		$this->nearby_characters[$character->id] = $character;
		
		Console::Log($character->name ." came into view");
	}
	
	public function RemoveNearbyCharacter($id)
	{
		Console::Log($this->nearby_characters[$id]->name ." left the view");
		unset($this->nearby_characters[$id]);

	}
	
	public function SetMe($character)
	{
		$this->me = $character;
		Console::Log("I am " . $character->name .", a lvl ". $character->level . " ". (($character->gender) ? "male" : "female"));
	}
	
	public function Face($direction)
	{
		$pack = "";
		$pack .= chr(Protocol::A['Player']);
		$pack .= chr(Protocol::F['Face']);
		$next_seq = $this->packet_processor->next_sequence();
		$pack .= Protocol::EncodeInteger($next_seq);
		$pack .= Protocol::EncodeInteger($direction, 1);
		$this->Send($pack);
		
		$this->me->direction = $direction;
	}
	
	public function GetNearbyCharacter($id)
	{
		return isset($this->nearby_characters[$id]) ? $this->nearby_characters[$id] : null;
	}
	
	public function OpenGlobal()
	{
		$pack = "";
		$pack .= chr(Protocol::A['Open']);
		$pack .= chr(Protocol::F['Global']);
		$next_seq = $this->packet_processor->next_sequence();
		$pack .= Protocol::EncodeInteger($next_seq);
		$pack .= Protocol::EncodeInteger(120, 1);
		$this->Send($pack);
	}
	
	public function GlobalMessageReceive($name, $message)
	{
		Console::Log("🌏 [". $name ."] ". $message);
		$chat_message = new ChatMessage();
		$chat_message->name = $name;
		$chat_message->message = $message;
		$chat_message->time = time();
		
		$this->chat_log["Global"][] = $chat_message;
	}
	
	public function PrivateMessageReceive($name, $message)
	{
		Console::Log("✉️ [". $name ."] ". $message);
		$chat_message = new ChatMessage();
		$chat_message->name = $name;
		$chat_message->message = $message;
		$chat_message->time = time();
		
		$this->chat_log["Private"][] = $chat_message;
	}
	
	public function LocalMessageReceive($id, $message)
	{
		Console::Log("💬 [". $this->nearby_characters[$id]->name ."] ". $message);
		
		$chat_message = new LocalMessage();
		$chat_message->character_id = $id;
		$chat_message->message = $message;
		$chat_message->time = time();
		
		//TODO: get players name
		
		$this->chat_log["Local"][] = $chat_message;
	}
	
	public function Send($data)
	{
		$len = Protocol::EncodeInteger(strlen($data), 2);
		$this->packet_processor->SetIsClient(true);
		$send_data = $len . $this->packet_processor->s_process($data);
		$this->server_socket->Send($send_data);
		if($this->config->get("Debug", "LogClientPackets"))
		{
			$packet = new Packet($data);
			$this->PrintPacket($packet, "outgoing");
		}
	}
	
	public function EncryptPassword($plain, $key)
	{
		$encrypted = "";
		$length = strlen($plain);
		for($i = 0; $i < $length; $i++)
		{
			$result = (($i + 1) * $key + ord($plain[$i])) & 0xFF; // Perform the calculation and extract the lower 8 bits
			$first = chr(65 + $result / 24);
			$second = chr(65 + $result % 24);
			$encrypted .= $first;
			$encrypted .= $second;
		}
		return $encrypted;	
	}

	public function DecryptPassword($encrypted, $key)
	{
		$decrypted = "";
		$length = strlen($encrypted);
		for ($i = 0; $i < $length; $i += 2)
		{
			$first = ord($encrypted[$i]) - 65;
			$second = ord($encrypted[$i + 1]) - 65;
			$result = ($first * 24) + $second;
			$result = ($result - (($i / 2) + 1) * $key) & 0xFF;
			$decrypted .= chr($result);
		}
		return $decrypted;
	}

	function PrintPacket($packet, $direction)
	{
		$action = $packet->get_type()->action;
		$family = $packet->get_type()->family;
		if(($direction == "outgoing" && in_array($packet->get_type()->name, $this->config->get("Debug", "IgnoreClientPackets"))) || ($direction == "incoming" && in_array($packet->get_type()->name, $this->config->get("Debug", "IgnoreServerPackets"))))
			return;
		$length = $packet->get_length();
		$arr = [];
		for ($i = 0; $i < $packet->get_length(); $i++) {
			$byteValue = ord($packet->get_data()[$i]);
			$arr[] = $byteValue;
		}
		Console::Log($packet->get_type()->name . " (". $family . " ". $action .") ". $length ." : \n" . $packet->pretty_data(), $direction);
	}
	
	public function Start()
	{
		$this->server_socket = new ServerSocket($this->config->get("Connection", "Host"), $this->config->get("Connection", "Port"));
		Console::Log("Connected to " . $this->config->get("Connection", "Host") . ":" . $this->config->get("Connection", "Port"));
		$this->packet_processor = new PacketProcessor(true);
		$this->packet_handler = new PacketHandler($this);
		
		// Send init packet
		$challenge = 17775;
		$this->packet_processor->RememberChallenge($challenge);
		$this->packet_processor->decrypt_key = $challenge % 252;
		$pack = "";
		$pack .= chr(Protocol::A['Init']);
		$pack .= chr(Protocol::F['Init']);
		$pack .= Protocol::EncodeInteger($challenge, 3);
		$version = $this->config->get("Client", "Version");
		for($i = 0; $i < strlen($version); $i++)
		{
			if($version[$i] == '.')
				continue;
			$pack .= Protocol::EncodeInteger(intval($version[$i]));
		}
		$pack .= Protocol::EncodeInteger(113);
		$pack .= Protocol::EncodeInteger(10);
		$hdid = $this->config->get("Client", "HDID");
		for($i = 0; $i < strlen($hdid); $i++)
		{
			$pack .= Protocol::EncodeInteger(ord($hdid[$i]));
		}
		$this->Send($pack);
		
		// Receive data and handle packets
		while(true)
		{
			$response = $this->server_socket->Receive();
			if($response == null)
			{
				if($this->state >= EOBot::STATE_INIT && (time() - $this->last_pong >= 2))
				{
					$pack = "";
					$pack .= chr(Protocol::A['Pong']);
					$pack .= chr(Protocol::F['Message']);
					$next_seq = $this->packet_processor->next_sequence();
					$pack .= Protocol::EncodeInteger($next_seq);
					$pack .= Protocol::EncodeInteger(rand(32,500), 2);
					$this->Send($pack);
					$this->last_pong = time();
				}
				
				continue;
			}
			$lenbytes = $response[0];
			$lenbytes .= $response[1];
			$length = Protocol::DecodeInteger($lenbytes);
			$rawdata = substr($response, 2, $length);
			$this->packet_processor->SetIsClient(false);
			$packet = $this->packet_processor->r_process($rawdata);
			if($this->config->get("Debug", "LogServerPackets"))
				$this->PrintPacket($packet, "incoming");
			$callback = array($this->packet_handler, $packet->get_type()->name);
			if (is_callable($callback)) {
				$callback($packet);
				$packet->set_pos(0);
			}
			else
			{
				if($this->config->get("Debug", "ShowUnhandledPackets"))
					Console::Log("Unhandled packet " . $packet->get_type()->name, "warning");
			}
			if($this->state >= EOBot::STATE_INIT && (time() - $this->last_pong >= 2))
			{
				$pack = "";
				$pack .= chr(Protocol::A['Pong']);
				$pack .= chr(Protocol::F['Message']);
				$next_seq = $this->packet_processor->next_sequence();
				$pack .= Protocol::EncodeInteger($next_seq);
				$pack .= Protocol::EncodeInteger(rand(32,500), 2);
				$this->Send($pack);
				$this->last_pong = time();
			}
			
		}
		
		
		
		$this->server_socket->Close();
	}
	
}

$bot = new EOBot();

$bot->Start();