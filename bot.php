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
require_once('src/Trade.class.php');

class HarvestBot
{
	const HARVEST_IDLE = 0;
	const HARVEST_WAIT_FOR_GATHER_PLAYER = 1;
	const HARVEST_GATHERING = 2;
	const HARVEST_WAIT_FOR_GATHER_SWAP = 3;
	
	private $eobot;
	private $state;
	private $requets_time;
	private $request_reply_time;
	private $current_node;
	private $unk1;
	private $unk2;
	private $unk3;
	private $unk4;
	
	
	function __construct(&$eobot)
	{
		$this->state = HarvestBot::HARVEST_IDLE;
		$this->eobot = $eobot;
		$this->request_time = time();
		$this->request_reply_time = time();
	}
	
	public function Gather_Player($packet)
	{
		$this->unk1 = $packet->get_int(4);
		$this->request_reply_time = time();
		$this->state = HarvestBot::HARVEST_GATHERING;
		
	}
	
	public function Gather_Agree($packet)
	{
		// TODO: this should be in gather swap but that packet doesnt arrive for some reason, maybe because we cant see the node
		//$this->request_reply_time = time();
		//$this->request_time = time();
		//$this->state = HarvestBot::HARVEST_IDLE;
	}
	
	public function Gather_Swap($packet)
	{
		$this->request_reply_time = time();
		$this->request_time = time();
		$this->state = HarvestBot::HARVEST_IDLE;
	}

	public function Process()
	{
		if(!isset($this->eobot->map) || $this->eobot->GetState() != EOBot::STATE_IN_GAME || $this->eobot->IsTrading())
		{
			return;
		}
		
		if($this->state == HarvestBot::HARVEST_IDLE)
		{
			$closest_node = null;
			$closest_distance = PHP_INT_MAX;
			
			if(!isset($this->current_node))
			{
				foreach ($this->eobot->map->gather_nodes as $node)
				{
					$distance = abs($this->eobot->me->map_x - $node->map_x) + abs($this->eobot->me->map_y - $node->map_y);

					if ($distance < $closest_distance)
					{
						$closest_distance = $distance;
						$closest_node = $node;
					}
				}
				if($closest_node === null)
					return;
				$this->current_node = $closest_node;
				$this->request_time = time();
			}
			$this->eobot->RequestNodeGather($this->current_node);
			$this->state = HarvestBot::HARVEST_WAIT_FOR_GATHER_PLAYER;
		}
		
		if($this->state == HarvestBot::HARVEST_GATHERING)
		{
			if(time() > $this->request_reply_time + $this->eobot->config->get("Harvesting", "HarvestDelay"))
			{
				$this->eobot->GatherNode($this->current_node, $this->unk1);
				$this->state = HarvestBot::HARVEST_WAIT_FOR_GATHER_SWAP;
			}
		}
		
		if(time() > $this->request_reply_time + 4)
		{
			$harvest_item = $this->eobot->GetItemById($this->eobot->config->get("Harvesting", "HarvestItem"));
			$amount = $this->eobot->me->inventory->GetAmount($harvest_item->id);
			
			if($amount > 0 && $this->eobot->TradeRequestPending() != true)
			{
				foreach($this->eobot->config->get("Game", "Masters") as $master_name)
				{
					$master = $this->eobot->GetNearbyCharacterByName($master_name);
					if($master !== null)
					{
						$this->eobot->RequestTrade($master, array(), array($harvest_item->id => 500));
						$this->request_reply_time = time();
						return;
					}
				}
			}
			$this->eobot->should_exit = true;
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
	private $chat_log;
	private $nearby_characters;
	public $me;
	private $items;
	private $trade;
	private $harvest_bot;
	public $map;
	public $should_exit;
	
	function __construct($config_path = "config.ini")
	{
		$this->should_exit = false;
		$this->config = new BotConfig($config_path);
		$this->last_pong = time();
		$this->last_pong_reply = time();
		$this->chat_log = array("Local" => array(), 
								"Global" => array(), 
								"Private" => array(), 
								"Party" => array(), 
								"Announce" => array(), 
								"System" => array());
		$nearby_characters = array();
		$jsonString = file_get_contents('items.json');
		$this->items = json_decode($jsonString, true);
		$this->trade = null;
		$this->harvest_bot = new HarvestBot($this);
		$this->map = null;

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
	
	public function IsTrading(){
		if($this->trade === null)
			return false;
		
		return $this->trade->GetState() > Protocol::TRADE_REQUESTED;
	}
	
	public function TradeRequestPending(){
		if($this->trade === null)
			return false;
		
		return $this->trade->GetState() == Protocol::TRADE_REQUESTED;
	}
	
	public function UpdateTrade($mine, $theirs)
	{
		if($this->trade !== null)
			$this->trade->TradeChanged($mine, $theirs);
	}
	
	public function AcceptTradeRequest($partner)
	{
		$trade = new Trade($this, $partner);
		
		$this->trade = $trade;
		
		$pack = "";
		$pack .= chr(Protocol::A['Accept']);
		$pack .= chr(Protocol::F['Trade']);
		$next_seq = $this->packet_processor->next_sequence();
		$pack .= Protocol::EncodeInteger($next_seq);
		$pack .= Protocol::EncodeInteger(0, 1);
		$pack .= Protocol::EncodeInteger($trade->GetPartner()->id, 2);
		$this->Send($pack);
	}
	
	public function TradeOpen()
	{
		if($this->trade !== null)
		{
			$this->trade->SetState(Protocol::TRADE_TRADING);
		}
	}
	
	public function TradeAddItem($id, $amount)
	{
		$pack = "";
		$pack .= chr(Protocol::A['Add']);
		$pack .= chr(Protocol::F['Trade']);
		$next_seq = $this->packet_processor->next_sequence();
		$pack .= Protocol::EncodeInteger($next_seq);
		$pack .= Protocol::EncodeInteger($id, 2);
		$pack .= Protocol::EncodeInteger(0, 1);
		$pack .= Protocol::EncodeInteger(0, 1);
		$pack .= Protocol::EncodeInteger(0, 1);
		$pack .= Protocol::EncodeInteger(0, 1);
		$pack .= Protocol::EncodeInteger(0, 1);
		$pack .= Protocol::EncodeInteger(0, 1);
		$pack .= Protocol::EncodeInteger($amount, 4);
		$this->Send($pack);
	}
	
	public function AcceptTrade()
	{
		$this->trade->SetState(Protocol::TRADE_ACCEPTED);
		$pack = "";
		$pack .= chr(Protocol::A['Agree']);
		$pack .= chr(Protocol::F['Trade']);
		$next_seq = $this->packet_processor->next_sequence();
		$pack .= Protocol::EncodeInteger($next_seq);
		$pack .= Protocol::EncodeInteger(1, 1);
		$this->Send($pack);
	}
	
	public function FinishTrade($items_lost, $items_gained)
	{
		foreach($items_lost as $key => $val)
		{
			$item = $this->GetItemById($key);
			$this->me->inventory->Remove($item, $val);
		}
		
		foreach($items_gained as $key => $val)
		{
			$item = $this->GetItemById($key);
			$this->me->inventory->Add($item, $val);
		}
		
		if($this->trade !== null)
		{
			unset($this->trade);
			$this->trade = null;
		}
	}
	
	public function CancelTrade()
	{
		if($this->trade->GetState() > Protocol::TRADE_REQUESTED || $this->trade === null)
		{
			$pack = "";
			$pack .= chr(Protocol::A['Close']);
			$pack .= chr(Protocol::F['Trade']);
			$next_seq = $this->packet_processor->next_sequence();
			$pack .= Protocol::EncodeInteger($next_seq);
			$pack .= Protocol::EncodeInteger(0, 1);
			$this->Send($pack);
		}
		if($this->trade !== null)
		{
			unset($this->trade);
			$this->trade = null;
		}
	}
	
	public function TradeClosed()
	{
		if($this->trade !== null)
		{
			unset($this->trade);
			$this->trade = null;
		}
	}
	
	public function RequestTrade($character, $expected_items, $giving_items)
	{
		if($this->trade !== null)
			return;
		
		$trade = new Trade($this, $character);
		$trade->SetExpectedItems($expected_items);
		$trade->SetGivingItems($giving_items);
		
		$this->trade = $trade;
		
		$pack = "";
		$pack .= chr(Protocol::A['Request']);
		$pack .= chr(Protocol::F['Trade']);
		$next_seq = $this->packet_processor->next_sequence();
		$pack .= Protocol::EncodeInteger($next_seq);
		$pack .= Protocol::EncodeInteger(138, 1);
		$pack .= Protocol::EncodeInteger($character->id, 2);
		$this->Send($pack);
	}
	
	public function Walk($direction)
	{
        $xoff = array(0 => 0, 1 => -1, 2 => 0, 3 => 1 );
		$yoff = array(0 => 1, 1 => 0, 2 => -1, 3 => 0 );
		$next_seq = $this->packet_processor->next_sequence();
		$pack .= Protocol::EncodeInteger($next_seq);
		$pack .= Protocol::EncodeInteger($direction);
		$pack .= Protocol::EncodeInteger(Protocol::timestamp(), 3);
		$pack .= Protocol::EncodeInteger($this->me->map_x + $xoff[$direction]);
		$pack .= Protocol::EncodeInteger($this->me->map_y + $yoff[$direction]);
		$this->Send($pack);
		
		$this->me->map_x = $this->me->map_x + $xoff[$direction];
		$this->me->map_y = $this->me->map_y + $yoff[$direction];
    }
	
	public function RequestNodeGather($node)
	{
		$pack = "";
		$pack .= chr(Protocol::A['Take']);
		$pack .= chr(Protocol::F['Gather']);
		$next_seq = $this->packet_processor->next_sequence();
		$pack .= Protocol::EncodeInteger($next_seq);
		$pack .= Protocol::EncodeInteger($node->id);
		$this->Send($pack);
	}
	
	public function GatherNode($node, $unk1)
	{
		$pack = "";
		$pack .= chr(Protocol::A['Accept']);
		$pack .= chr(Protocol::F['Gather']);
		$next_seq = $this->packet_processor->next_sequence();
		$pack .= Protocol::EncodeInteger($next_seq);
		$pack .= Protocol::EncodeInteger($unk1, 4);
		$pack .= Protocol::EncodeInteger($node->id, 1);
		$this->Send($pack);
	}
	
	public function GetItemByName($name)
	{
		foreach($this->items as $key => $val)
		{
			if(strtolower($this->items[$key]["name"]) == strtolower($name))
			{
				$item = new EOItem();
				$item->id = $key;
				$item->name = $val["name"];
				
				return $item;
			}
		}
		return null;
	}
	
	public function GetItemById($id)
	{
		if(isset($this->items[$id]))
		{
			$item = new EOItem();
			$item->id = $id;
			$item->name = $this->items[$id]["name"];
			
			return $item;
		}
		else
		{
			return null;
		}
	}
	
	public function TalkPublic($message)
	{
		$pack = "";
		$pack .= chr(Protocol::A['Report']);
		$pack .= chr(Protocol::F['Talk']);
		$next_seq = $this->packet_processor->next_sequence();
		$pack .= Protocol::EncodeInteger($next_seq);
		$pack .= $message;
		$this->Send($pack);
	}
	
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
	
	public function GetNearbyCharacter($id)
	{
		return isset($this->nearby_characters[$id]) ? $this->nearby_characters[$id] : null;
	}
	
	public function GetNearbyCharacterByName($name)
	{
		if(!isset($this->nearby_characters))
			return null;
		
		foreach($this->nearby_characters as $key => $val)
		{
			if(strtolower($name) == strtolower($val->name))
			{
				return $this->nearby_characters[$key];
			}
		}
		
		return null;
	}
	
	public function AddInventoryItem($item, $amount)
	{
		if($item === null)
			return;
		$this->me->inventory->Add($item, $amount);
	}
	
	public function RemoveInventoryItem($item, $amount)
	{
		$this->me->inventory->Remove($item, $amount);
	}
	
	public function SetMe($character)
	{
		$this->me = $character;
		Console::Log("I am " . $character->name .", a lvl ". $character->level . " ". (($character->gender) ? "male" : "female"));
	}
	
	public function LoadMap($id)
	{
		$this->map = new EOMap($id);
	}
	
	public function ResourceGrew($node_id, $amount)
	{
		if(isset($this->map))
		{
			if(isset($this->map->nodes[$node_id]))
			{
				$this->map->nodes[$node_id]->amount = $amount;
			}
		}
	}
	
	public function ResourceGathered($node_id, $amount)
	{
		$this->ResourceGrew($node_id, $amount);
	}
	
	public function ObtainedGatherItem($item, $amount = 1)
	{
		$this->AddInventoryItem($item, $amount);
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
	
	public function Sit()
	{
		if($this->me->sitting == 0)
		{
						$pack = "";
			$pack .= chr(Protocol::A['Request']);
			$pack .= chr(Protocol::F['Sit']);
			$next_seq = $this->packet_processor->next_sequence();
			$pack .= Protocol::EncodeInteger($next_seq);
			$pack .= Protocol::EncodeInteger(1, 1);
			$pack .= Protocol::EncodeInteger($this->me->map_x, 1);
			$pack .= Protocol::EncodeInteger($this->me->map_y, 1);
			$this->Send($pack);
			
			$this->me->sitting = 1;
		}
	}
	
	public function Stand()
	{
		if($this->me->sitting == 1)
		{			
			$pack = "";
			$pack .= chr(Protocol::A['Request']);
			$pack .= chr(Protocol::F['Sit']);
			$next_seq = $this->packet_processor->next_sequence();
			$pack .= Protocol::EncodeInteger($next_seq);
			$pack .= Protocol::EncodeInteger(2, 1);
			$this->Send($pack);
			
			$this->me->sitting = 0;
		}
	}
	
	public function UseItem($id)
	{
		$pack = "";
		$pack .= chr(Protocol::A['Use']);
		$pack .= chr(Protocol::F['Item']);
		$next_seq = $this->packet_processor->next_sequence();
		$pack .= Protocol::EncodeInteger($next_seq);
		$pack .= Protocol::EncodeInteger($id, 2);
		$this->Send($pack);
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
	
	function Process()
	{
		
	}
	
	public function Start()
	{
		$this->server_socket = new ServerSocket($this->config->get("Connection", "Host"), $this->config->get("Connection", "Port"), $this);
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
		
		if($this->config->get("Client", "HDIDRandom"))
		{
			$len = rand(8,12);
			$pack .= Protocol::EncodeInteger($len);
			for($i = 0; $i < $len; $i++)
			{
				$pack .= Protocol::EncodeInteger(ord(chr(rand(0,9))));
			}
		}
		else
		{
			$hdid = $this->config->get("Client", "HDID");
			$pack .= Protocol::EncodeInteger(strlen($hdid));
			for($i = 0; $i < strlen($hdid); $i++)
			{
				$pack .= Protocol::EncodeInteger(ord($hdid[$i]));
			}
		}
		$this->Send($pack);
		
		// Receive data and handle packets
		while($this->should_exit === false)
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
				
				if(time() > $this->last_pong_reply + 3)
				{
					break;
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
			
			$callbacks = array();
			$callbacks[] = array($this->packet_handler, $packet->get_type()->name);
			if($this->config->get("Harvesting", "IsHarvestBot"))
			{
				$callbacks[] = array($this->harvest_bot, $packet->get_type()->name);
			}
			$handled = false;
			foreach($callbacks as $callback)
			{
				if (is_callable($callback)) {
					$packet->set_pos(0);
					$callback($packet);
					$handled = true;
				}	
			}
			if(!$handled)
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
			
			if($this->trade !== null)
			{
				$this->trade->Process();
			}
			
			if($this->config->get("Harvesting", "IsHarvestBot"))
			{
				$this->harvest_bot->Process();
			}
			
			$this->Process();
			
		}
		
		$this->server_socket->Close();
	}
	
}

//$bot = new EOBot();

//$bot->Start();