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
	
	public function Face($args)
	{
		$direction_map = array("up" => Protocol::DIRECTION_UP, "down" => Protocol::DIRECTION_DOWN, "left" => Protocol::DIRECTION_LEFT, "right" => Protocol::DIRECTION_RIGHT);
		if(array_key_exists($args[0], $direction_map))
		{
			$this->eobot->Face($direction_map[$args[0]]);
		}
	}
}
