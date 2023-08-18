<?php

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
			$character = new EOCharacterPreview();
			$character->name = $packet->get_string();
			$character->date_created = $packet->get_string();
			$character->last_login = $packet->get_string();
			
			$character->id = $packet->get_int(4);
			/*$character->level = $packet->get_int(2);
			$character->gender = $packet->get_int(1);
			$character->hairstyle = $packet->get_bytes(1);
			$character->haircolor = $packet->get_bytes(1);
			$character->skin = $packet->get_int(1);
			$character->admin_level = $packet->get_int(1);
			for($i = 0; $i < 5; $i++)
			{
				$character->paperdoll = array();
				$character->paperdoll[] = $packet->get_int(1);
			}*/
			while($packet->get_bytes(1, false) != Protocol::COMMA)
			{
				$packet->get_int(1);
			}
			$packet->get_int(1); // 255
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
			$this->eobot->should_exit = true;
		}
		
		//Console::Log("Bytes left: " . $packet-);
		
	}
	
	public function Channel_Player($packet)
	{
		// TODO: fix this
		// it breaks our harvest bot so disconnect
		$this->eobot->should_exit = true;
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
		$map_id = $packet->get_int(2);
		
		$this->eobot->LoadMap($map_id);
		
		$pack = "";
		$pack .= chr(Protocol::A['Spec']);
		$pack .= chr(Protocol::F['Welcome']);
		$next_seq = $this->packet_processor->next_sequence();
		$pack .= Protocol::EncodeInteger($next_seq);
		$pack .= Protocol::EncodeInteger($int1, 3);
		$pack .= Protocol::EncodeInteger($int2, 4);
		$this->eobot->Send($pack);
		
	}
	
	public function Welcome_Accept($packet)
	{
		$n = $packet->get_int(1);
		$this->packet_processor->UpdateEncryptionFromServer($n);
		
		while ($packet->get_bytes(1, false) != Protocol::COMMA)
		{
			// pre-data 
			$packet->ignore(1);
		}
		$packet->ignore(1); //255
		
		while ($packet->get_bytes(1, false) != Protocol::COMMA)
		{
			// pre-data2
			$packet->ignore(1);
		}
		$packet->ignore(1); //255
		
		$motd = $packet->get_string();
		
		$unk1 = $packet->get_int(1);
					
		while ($packet->get_bytes(1, false) != Protocol::COMMA)
		{
			//paperdoll
			$item_id = $packet->get_int(2);
			$properties = $packet->get_bytes(1);
			$charge = $packet->get_bytes(1);
			$tier = $packet->get_bytes(1);
			$unk_property1 = $packet->get_bytes(1);
			$unk_property2 = $packet->get_bytes(1);
			$unk_property3 = $packet->get_bytes(1);
		}
		$packet->ignore(1); //255
		
		$weight = $packet->get_int(2);
		$max_weight = $packet->get_int(2);
		
		$items = array();
		while ($packet->get_bytes(1, false) != Protocol::COMMA)
		{
			//inventory
			$item_id = $packet->get_int(2);
			$properties = $packet->get_bytes(1);
			$charge = $packet->get_bytes(1);
			$tier = $packet->get_bytes(1);
			$unk_property1 = $packet->get_bytes(1);
			$unk_property2 = $packet->get_bytes(1);
			$unk_property3 = $packet->get_bytes(1);
			$amount = $packet->get_int(4);
		
			$item = $this->eobot->GetItemById($item_id);
			Console::Log("Inventory item: ". $amount . " ". $item->name);
			
			$items[] = array("data" => $item, "amount" => $amount);
		}
		$packet->ignore(1); //255
		
		while ($packet->get_bytes(1, false) != Protocol::COMMA)
		{
			$packet->ignore(1);
			//spells
		}
		$packet->ignore(1); //255
		
		$num_players = $packet->get_int(1);
		$packet->ignore(1); //255
		
		for($i = 0; $i < $num_players; $i++)
		{
			if($i + 1 == $num_players) // it's us
			{
				$character = $this->eobot->ReadCharacterData($packet);
				$this->eobot->SetMe($character);
				foreach($items as $item)
				{
					$this->eobot->AddInventoryItem($item["data"], $item["amount"]);
				}
				
				print_r($character);
			}
			else
			{
				$character = $this->eobot->ReadCharacterData($packet);
				$this->eobot->AddNearbyCharacter($character);
			}
		}
		
		while ($packet->get_bytes(1, false) != Protocol::COMMA) {
			$packet->ignore(1);
			//NPCs
		}
		$packet->ignore(1); //255
		
		while ($packet->get_bytes(1, false) != Protocol::COMMA) {
			$packet->ignore(1);
			//unk
		}
		$packet->ignore(1); //255
		
		
		while ($packet->get_bytes(1, false) != Protocol::COMMA) {
			// gatherables
			$res_id = $packet->get_int(1);
			$res_amount = $packet->get_int(1);
			
			Console::Log("Resource: " . $res_id . " " . $res_amount);
		}
		$packet->ignore(1); //255
		
		//2 more 255's after this, not sure what for yet maybe harvest stuff
		
		Console::Log("MotD: ". $motd);
		
		if($this->eobot->config->get("Game", "OpenGlobalOnLogin"))
			$this->eobot->OpenGlobal();
		
		$this->eobot->SetState(EOBot::STATE_IN_GAME);
	}
	
	public function Gather_Time($packet)
	{
		// resources grow
		while($packet->bytes_left() > 0)
		{
			$node_id = $packet->get_int(1);
			$amount = $packet->get_int(1);
			
			$this->eobot->ResourceGrew($node_id, $amount);
		}
	}
	
	public function Gather_Agree($packet)
	{
		// resource types: cotton: 1, twigs: 5
		// reource harvested by player
		$node_id = $packet->Get_int(1);
		$amount = $packet->Get_int(1);
		$res_type = $packet->Get_int(1);
		
		$this->eobot->ResourceGathered($node_id, $amount);
	}
	
	public function Gather_Swap($packet)
	{
		// we got an item from gathering
		$item_id = $packet->get_int(2);
		$properties = $packet->get_bytes(1);
		$charge = $packet->get_bytes(1);
		$tier = $packet->get_bytes(1);
		$unk_property1 = $packet->get_bytes(1);
		$unk_property2 = $packet->get_bytes(1);
		$unk_property3 = $packet->get_bytes(1);
		
		$item = $this->eobot->GetItemById($item_id);
		$this->eobot->ObtainedGatherItem($item, 1);
	}
	
	public function Trade_Reply($packet)
	{
		$char1_id = $packet->get_int(2);
		$char1_items = array();
		while ($packet->get_bytes(1, false) != Protocol::COMMA)
		{
			$item_id = $packet->get_int(2);
			$properties = $packet->get_bytes(1);
			$charge = $packet->get_bytes(1);
			$tier = $packet->get_bytes(1);
			$unk_property1 = $packet->get_bytes(1);
			$unk_property2 = $packet->get_bytes(1);
			$unk_property3 = $packet->get_bytes(1);
			$item_amount = $packet->get_int(4);
			
			$char1_items[$item_id] = $item_amount; 
		}
		$packet->ignore(1); // 255
		
		$char2_id = $packet->get_int(2);
		$char2_items = array();
		while ($packet->get_bytes(1, false) != Protocol::COMMA)
		{
			$item_id = $packet->get_int(2);
			$properties = $packet->get_bytes(1);
			$charge = $packet->get_bytes(1);
			$tier = $packet->get_bytes(1);
			$unk_property1 = $packet->get_bytes(1);
			$unk_property2 = $packet->get_bytes(1);
			$unk_property3 = $packet->get_bytes(1);
			$item_amount = $packet->get_int(4);
			
			$char2_items[$item_id] = $item_amount; 
		}
		$packet->ignore(1); // 255
		
		if($char1_id == $this->eobot->me->id)
		{
			$this->eobot->UpdateTrade($char1_items, $char2_items);
		}
		else
		{
			$this->eobot->UpdateTrade($char2_items, $char1_items);
		}
	}
	
	public function Trade_Use($packet)
	{
		$char1_id = $packet->get_int(2);
		$char1_items = array();
		while ($packet->get_bytes(1, false) != Protocol::COMMA)
		{
			$item_id = $packet->get_int(2);
			$properties = $packet->get_bytes(1);
			$charge = $packet->get_bytes(1);
			$tier = $packet->get_bytes(1);
			$unk_property1 = $packet->get_bytes(1);
			$unk_property2 = $packet->get_bytes(1);
			$unk_property3 = $packet->get_bytes(1);
			$item_amount = $packet->get_int(4);
			
			$char1_items[$item_id] = $item_amount; 
		}
		$packet->ignore(1); // 255
		
		$char2_id = $packet->get_int(2);
		$char2_items = array();
		while ($packet->get_bytes(1, false) != Protocol::COMMA)
		{
			$item_id = $packet->get_int(2);
			$properties = $packet->get_bytes(1);
			$charge = $packet->get_bytes(1);
			$tier = $packet->get_bytes(1);
			$unk_property1 = $packet->get_bytes(1);
			$unk_property2 = $packet->get_bytes(1);
			$unk_property3 = $packet->get_bytes(1);
			$item_amount = $packet->get_int(4);
			
			$char2_items[$item_id] = $item_amount; 
		}
		$packet->ignore(1); // 255
		
		if($char1_id == $this->eobot->me->id)
		{
			$this->eobot->FinishTrade($char1_items, $char2_items);
		}
		else
		{
			$this->eobot->FinishTrade($char2_items, $char1_items);
		}
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
	
	public function Trade_Close($packet)
	{
		$id = $packet->get_int(2);
		
		$this->eobot->TradeClosed();
	}
	
	public function Talk_Spec($packet)
	{
		$name = $packet->get_string();
		$message = $packet->get_string();
		
		$this->eobot->GlobalMessageReceive($name, $message);
	}
	
	public function Talk_Player($packet)
	{
		$id = $packet->get_int(2);
		$message = $packet->get_string();
		
		$this->eobot->LocalMessageReceive($id, $message);
	}
	
	public function Talk_tell($packet)
	{
		$name = $packet->get_string();
		$message = $packet->get_string();
		
		$this->eobot->PrivateMessageReceive($name, $message);
		
		if($message[0] == '#')
		{
			if(in_array($name, $this->eobot->config->get("Game", "Masters")))
			{
				$caller = $this->eobot->GetNearbyCharacterByName($name);
				
				$message_split = explode(' ', $message);
				$command = $message_split[0];
				$command = substr($command, 1, strlen($command));
				array_shift($message_split);
				$callback = array($this->command_handler, $command);
				if (is_callable($callback)) {
					$callback($message_split, $caller);
				}
				else
				{
					Console::Log("Unknown command " . $command . ".");
				}
			}
		}
	}
	
	public function Trade_Request($packet)
	{
		if($this->eobot->IsTrading())
			return;
		
		$packet->ignore(1); // always 138
		$char_id = $packet->get_int(2);
		
		$partner = $this->eobot->GetNearbyCharacter($char_id);
		
		if(in_array($partner->name, $this->eobot->config->get("Game", "Masters")))
		{
			Console::Log($partner->name ." requested a trade");
			
			$this->eobot->AcceptTradeRequest($partner);
		}
	}
	
	public function Trade_Open($packet)
	{
		$victim_id = $packet->get_int(2);
		$victim_name = $packet->get_string();
		
		$starter_id = $packet->get_int(2);
		$starter_name = $packet->get_string();
		
		$this->eobot->TradeOpen();
	}
	
	public function Welcome_Agree($packet)
	{
		$news = $packet->get_string();
		Console::Log("📰 ". $news);
	}
	
	public function Talk_Switch($packet)
	{
		while($packet->bytes_left() > 0)
		{
			$name = $packet->get_string();
			$message = $packet->get_string();
			
			$this->eobot->GlobalMessageReceive($name, $message);
		}
	}
	
	public function Range_Reply($packet)
	{
		$num_players = $packet->get_int(1);
		$packet->ignore(1); //255
		
		for($i = 0; $i < $num_players; $i++)
		{
			$character = $this->eobot->ReadCharacterData($packet);
			$this->eobot->AddNearbyCharacter($character);
		}
	}
	
	public function Walk_Player($packet)
	{
		$char_id = $packet->get_int(2);
		
		$new_x = $packet->get_int(1);
		$new_y = $packet->get_int(1);
		$direction = $packet->get_int(1); // not 100% sure
		
		$distance = abs($new_x - $this->eobot->me->map_x) + abs($new_y - $this->eobot->me->map_y);
		
		$character = $this->eobot->GetNearbyCharacter($char_id);
		if($character == null && $distance < 17)
		{
			$pack = "";
			$pack .= chr(Protocol::A['Request']);
			$pack .= chr(Protocol::F['PlayerRange']);
			$next_seq = $this->packet_processor->next_sequence();
			$pack .= Protocol::EncodeInteger($next_seq);
			$pack .= Protocol::EncodeInteger($char_id);
			$this->eobot->Send($pack);
		}
		else if($character != null)
		{
			$character->map_x = $new_x;
			$character->map_y = $new_y;
			$character->direction = $direction;
			
			if($distance >= 17)
				$this->eobot->RemoveNearbyCharacter($char_id);
		}
	}
	
	public function Face_Player($packet)
	{
		$char_id = $packet->get_int(2);
		
		$direction = $packet->get_int(1);
		
		$character = $this->eobot->GetNearbyCharacter($char_id);
		if($character == null)
		{
			$pack = "";
			$pack .= chr(Protocol::A['Request']);
			$pack .= chr(Protocol::F['PlayerRange']);
			$next_seq = $this->packet_processor->next_sequence();
			$pack .= Protocol::EncodeInteger($next_seq);
			$pack .= Protocol::EncodeInteger($char_id);
			$this->eobot->Send($pack);
		}
		else
		{
			$character->direction = $direction;
		}
	}
	
	public function Sit_Player($packet, $sitstate = 1)
	{
		$char_id = $packet->get_int(2);
				
		$new_x = $packet->get_int(1);
		$new_y = $packet->get_int(1);
		$direction = $packet->get_int(1);
		
		$character = $this->eobot->GetNearbyCharacter($char_id);
		if($character == null)
		{
			$pack = "";
			$pack .= chr(Protocol::A['Request']);
			$pack .= chr(Protocol::F['PlayerRange']);
			$next_seq = $this->packet_processor->next_sequence();
			$pack .= Protocol::EncodeInteger($next_seq);
			$pack .= Protocol::EncodeInteger($char_id);
			$this->eobot->Send($pack);
		}
		else
		{
			$character->map_x = $new_x;
			$character->map_y = $new_y;
			$character->direction = $direction;
			$character->sitting = $sitstate;
		}
	}
	
	public function Chair_Player($packet)
	{
		$this->Sit_Player($packet, 2);
	}
	
	public function Sit_Remove($packet)
	{
		$char_id = $packet->get_int(2);
				
		$new_x = $packet->get_int(1);
		$new_y = $packet->get_int(1);
		
		$character = $this->eobot->GetNearbyCharacter($char_id);
		if($character == null)
		{
			$pack = "";
			$pack .= chr(Protocol::A['Request']);
			$pack .= chr(Protocol::F['PlayerRange']);
			$next_seq = $this->packet_processor->next_sequence();
			$pack .= Protocol::EncodeInteger($next_seq);
			$pack .= Protocol::EncodeInteger($char_id);
			$this->eobot->Send($pack);
		}
		else
		{
			$character->map_x = $new_x;
			$character->map_y = $new_y;
			$character->sitting = 0;
		}	
	}
	
	public function Chair_Remove($packet)
	{
		$this->Sit_Remove($packet);
	}
	
}
