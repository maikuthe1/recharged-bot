<?php

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
	
	public function Say($args, $caller)
	{
		if(count($args, $caller) == 0)
			return;
		$message = implode(' ', $args);
		$this->eobot->TalkPublic($message);
	}
	
	public function Give($args, $caller)
	{
		if($caller === null)
			return;
		
		$message = implode(' ', $args);
		$giving_items = array();
		$expected_items = array();
		
		$boom = explode(", ", $message);
		foreach($boom as $line)
		{
			$split = explode(" ", $line);
			$amount = $split[0];
			unset($split[0]);
			$name = implode(" ", $split);
			
			$item = $this->eobot->GetItemByName($name);
			if($item !== null)
			{
				$giving_items[$item->id] = $amount;
			}
		}
		
		if(count($giving_items) > 0)
		{
			$this->eobot->RequestTrade($caller, $expected_items, $giving_items);
		}
	}
	
	public function Reconf($args, $caller)
	{
		if(count($args, $caller) > 0)
		{
			$this->eobot->config = new BotConfig($args[0]);
		}
		else
		{
			$this->eobot->config = new BotConfig();
		}
	}
	
	public function Item($args, $caller)
	{
		$item = $this->eobot->GetItemByName(implode(" ", $args));
		if($item !== null)
		{
			$this->eobot->TalkPublic($item->id);
		}
		else
		{
			$this->eobot->TalkPublic("couldn't find it");
		}
	}
	
	public function SetOpt($args, $caller)
	{
		$value = $args[1];
		if($value == "true")
			$value = true;
		if($value == "false")
			$value = false;
		
		$this->eobot->config->set($args[0], $value);
	}
	
	public function Face($args, $caller)
	{
		$direction_map = array("up" => Protocol::DIRECTION_UP, "down" => Protocol::DIRECTION_DOWN, "left" => Protocol::DIRECTION_LEFT, "right" => Protocol::DIRECTION_RIGHT);
		if(array_key_exists($args[0], $direction_map))
		{
			$this->eobot->Face($direction_map[$args[0]]);
		}
	}
}
