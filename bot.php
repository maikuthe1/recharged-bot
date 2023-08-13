<?php
require_once('src/Packet.class.php');
require_once('src/PacketType.class.php');
require_once('src/PacketProcessor.class.php');
require_once('src/Protocol.class.php');
require_once('src/EOStructures.php');

class BotConfig
{
	private $config = array();
	function __construct($config_path = "config.ini")
	{
		$this->Load($config_path);
	}
	
	public function Load($config_path)
	{
		if(!file_exists($config_path))
		{
			throw new Exception("Config file not found.");
		}
		$this->config = parse_ini_file($config_path, true);
	}
	
	public function get($section, $key)
	{
		if (isset($this->config[$section][$key])) {
            return $this->config[$section][$key];
        }
		Console::Warning("Config ". $section . " -> " . $key . " not found.");
        return null;
	}
}

class Console
{
    public static function Log($message, $logType = "info")
    {
        $colors = array(
            'outgoing' => "➡️ \033[32m", // Green for outgoing
            'incoming' => "⬅️ \033[33m", // Red for incoming
            'info' => "\033[37m",     // White for info
            'warning' => "⚠️ \033[38;5;208m",   // Yellow for warnings
            'error' => "🛑 \033[31m"   // Red for errors
        );

        $resetColor = "\033[0m";
        $timestamp = date('H:i:s'); // Get the current time in HH:MM:SS format

        if (array_key_exists($logType, $colors)) {
            $logTag = isset($logTags[$logType]) ? $logTags[$logType] : '[ ] '; // Default to [ ] if the log type doesn't have a specific tag
            $logMessage = '['.$timestamp . '] ' . $colors[$logType] . $message . $resetColor . "\n";
        } else {
            // Default to white with [ ] for unknown log types
            $logMessage = '['.$timestamp . '] ' . $colors['info'] . $message . $resetColor . "\n";
        }

        // Print to console
        echo $logMessage;

        // Append to log file
        $logFileName = 'log.txt';
        file_put_contents($logFileName, Console::StripColorCodes($logMessage), FILE_APPEND);
    }

    public static function Warning($message)
    {
        Console::Log($message, "warning");
    }
	
	public static function StripColorCodes($string)
	{
		return preg_replace('/\033\[[0-9;]*m/', '', $string);
	}
}

class ServerSocket
{
    private $socket;
    private $buffer_size = 8192;
    private $data;

    function __construct($host, $port)
    {
        $this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if (!socket_connect($this->socket, $host, $port)) {
            Console::Log("Connect to server failed", "error");
            exit(1);
        }
    }

    public function Receive()
    {
        $readSockets = [$this->socket];
        $writeSockets = [];
        $exceptSockets = [];

        // Wait for the socket to become readable or until the timeout is reached
        if (socket_select($readSockets, $writeSockets, $exceptSockets, 1) === false) {
            // Handle socket select error
        } elseif (in_array($this->socket, $readSockets)) {
            $this->data = socket_read($this->socket, $this->buffer_size);
            if ($this->data === false || strlen($this->data) === 0) {
                // Client/server disconnected or error occurred
                Console::Log("Server disconnected", "error");
                exit(1);
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

class CharacterPreview
{
	public $name;
	public $date_created;
	public $last_login;
	public $id;
	public $level;
	public $gender;
	public $hairstyle;
	public $haircolor;
	public $skin;
	public $admin_level;
	public $paperdoll;
}



class CommandHandler
{
	private $eobot;
	private $packet;
	private $packet_processor;
	
	function __construct(&$eobot)
	{
		$this->eobot = $eobot;
		$this->packet_processor = $this->eobot->GetPacketProcessor();
	}
	
	public function Say($args)
	{
		if(count($args) == 0)
			return;
		$message = implode(' ', $args);
		$pack = "";
		$pack .= chr(Protocol::A['Report']);
		$pack .= chr(Protocol::F['Talk']);
		$next_seq = $this->packet_processor->next_sequence();
		$pack .= Protocol::EncodeInteger($next_seq);
		$pack .= $message;
		$this->eobot->Send($pack);
	}
	
	public function Reconf($args)
	{
		if(count($args) > 0)
		{
			$this->eobot->config = new BotConfig($args[0]);
		}
		else
		{
			$this->eobot->config = new BotConfig();
		}
	}
}

class PacketHandler
{
	private $eobot;
	private $packet;
	private $packet_processor;
	private $command_handler;
	
	function __construct(&$eobot)
	{
		$this->eobot = $eobot;
		$this->packet_processor = $this->eobot->GetPacketProcessor();
		$this->command_handler = new CommandHandler($this->eobot);
	}
	
	public function Init_Init($packet)
	{
		$init_reply = $packet->get_int(1);
		if ($init_reply == Protocol::INIT_OK) // INIT_OK
		{
			$this->eobot->SetState(EOBot::STATE_INIT);
			$seq1 = $packet->get_int(1);
			$seq2 = $packet->get_int(1);
			
			$server_encval = $packet->get_int(2);
			$client_encval = $packet->get_int(2);
			$player_id = $packet->get_int(2);
			$challenge_response = $packet->get_int(3);
			$extra_byte = ord($packet->get_bytes(1));
			
			$this->packet_processor->SetupEncryptionFromInit($server_encval, $client_encval);
			$this->packet_processor->set_encoding($seq1, $seq2);
			$this->packet_processor->decrypt_key += $extra_byte;
			
			// Send connection accept Packet
			$pack = "";
			$pack .= chr(Protocol::A['Accept']);
			$pack .= chr(Protocol::F['Connection']);
			$next_seq = $this->packet_processor->next_sequence();
			$pack .= Protocol::EncodeInteger($next_seq);
			$pack .= Protocol::EncodeInteger($this->packet_processor->GetClientEncVal(), 2);
            $pack .= Protocol::EncodeInteger($this->packet_processor->GetServerEncVal(), 2);
            $pack .= Protocol::EncodeInteger($player_id, 2);
			$this->eobot->Send($pack);
			sleep(1);
			
			// Send login Packet
			$key = $this->packet_processor->decrypt_key + 124;
			$pack = "";
			$pack .= chr(Protocol::A['Request']);
			$pack .= chr(Protocol::F['Login']);
			$next_seq = $this->packet_processor->next_sequence();
			$pack .= Protocol::EncodeInteger($next_seq);
			$pack .= $this->eobot->config->get("Account", "Account") . Protocol::COMMA;
			$pack .= $this->eobot->EncryptPassword($this->eobot->config->get("Account", "Password"), $key) . Protocol::COMMA;
			$this->eobot->Send($pack);
		}
		else
		{
			Console::Log($packet->get_string());
		}
		// TODO other replies and other types like online list
	}
	
	public function Login_Accept($packet)
	{
		$this->eobot->last_pong_reply = time();
		$this->eobot->SetState(EOBot::STATE_LOGGED_IN);
		$scrambled_name = $this->eobot->config->get("Account", "Account");
		for($i = 1; $i < strlen($scrambled_name); $i++)
			$scrambled_name[$i] = '*';
		Console::Log("Logged in to account ". $scrambled_name);
		$packet->ignore(15);
		$num_characters = $packet->get_int(1);
		$packet->ignore(1); // 255
		
		$characters = array();
		while($packet->bytes_left() > 0)
		{
			$character = new CharacterPreview();
			$character->name = $packet->get_string();
			$character->date_created = $packet->get_string();
			$character->last_login = $packet->get_string();
			$character->id = $packet->get_int(4);
			$character->level = $packet->get_int(2);
			$character->gender = $packet->get_int(1);
			$character->hairstyle = $packet->get_bytes(1);
			$character->haircolor = $packet->get_bytes(1);
			$character->skin = $packet->get_int(1);
			$character->admin_level = $packet->get_int(1);
			for($i = 0; $i < 5; $i++)
			{
				$character->paperdoll = array();
				$character->paperdoll[] = $packet->get_int(1);
			}
			$characters[$character->name] = $character;
		}
		//print_r($characters);
		
		$character_name = $this->eobot->config->get("Account", "Character");
		if(isset($characters[$character_name]))
		{
			$pack = "";
			$pack .= chr(Protocol::A['Request']);
			$pack .= chr(Protocol::F['Welcome']);
			$next_seq = $this->packet_processor->next_sequence();
			$pack .= Protocol::EncodeInteger($next_seq);
			$pack .= Protocol::EncodeInteger($characters[$character_name]->id, 4);
			$random_num = rand(0, 198) + 2;
			$pack .= Protocol::EncodeInteger($random_num);
			$this->eobot->Send($pack);
			$this->packet_processor->UpdateEncryptionFromClient($random_num);
		}
		else
		{
			Console::Log("Character " . $character_name . " not found", "error");
			exit(1);
		}
		
		//Console::Log("Bytes left: " . $packet-);
		
	}
	
	public function Message_Net242($packet)
	{
		$this->eobot->last_pong_reply = time();
	}
	
	public function Welcome_Reply($packet)
	{
		$packet->ignore(2);
		$int1 = $packet->get_int(2);
		$int2 = $packet->get_int(4);
		
		$pack = "";
		$pack .= chr(Protocol::A['Spec']);
		$pack .= chr(Protocol::F['Welcome']);
		$next_seq = $this->packet_processor->next_sequence();
		$pack .= Protocol::EncodeInteger($next_seq);
		$pack .= Protocol::EncodeInteger($int1, 3);
		$pack .= Protocol::EncodeInteger($int2, 4);
		$this->eobot->Send($pack);
		
		if($this->eobot->config->get("Game", "OpenGlobalOnLogin"))
			$this->eobot->OpenGlobal();
	}
	
	public function Welcome_Accept($packet)
	{
		$this->eobot->SetState(EOBot::STATE_IN_GAME);
		$n = $packet->get_int(1);
		$this->packet_processor->UpdateEncryptionFromServer($n);
		
		Console::Log("Loaded map");
	}
	
	public function Connection_Player($packet)
	{
		$s1 = $packet->get_int(2);
		$s2 = $packet->get_int(1);

		$new_seq = (($s1 - $s2) + $this->packet_processor->seq_counter);
		$this->packet_processor->set_seq($new_seq);
		$this->packet_processor->sequence_target = 10 - $this->packet_processor->seq_counter;
		$this->packet_processor->seq_counter = 0;
		
		$pack = "";
		$pack .= chr(Protocol::A['Ping']);
		$pack .= chr(Protocol::F['Connection']);
		$next_seq = $this->packet_processor->next_sequence();
		$pack .= Protocol::EncodeInteger($next_seq);
		$pack .= Protocol::EncodeInteger(106, 1);
		$this->eobot->Send($pack);
	}
	
	public function Talk_Spec($packet)
	{
		$name = $packet->get_string();
		$message = $packet->get_string();
		
		Console::Log("🌏 ". $name .": ". $message);
	}
	
	public function Talk_tell($packet)
	{
		$name = $packet->get_string();
		$message = $packet->get_string();
		
		if($message[0] == '#')
		{
			if(in_array($name, $this->eobot->config->get("Game", "Masters")))
			{
				$message_split = explode(' ', $message);
				$command = $message_split[0];
				$command = substr($command, 1, strlen($command));
				array_shift($message_split);
				$callback = array($this->command_handler, $command);
				if (is_callable($callback)) {
					$callback($message_split);
				}
				else
				{
					Console::Log("Unhandled command " . $command . ".");
				}
			}
		}
	}
	
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
	
	public function SetState($new_state){ $this->state = $new_state; }
	
	public function GetState(){ return $this->state; }
	
	public function GetPacketProcessor(){ return $this->packet_processor; }
	
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
	
	function __construct()
	{
		$this->config = new BotConfig();
		$this->last_pong = time();
		$this->last_pong_reply = time();
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