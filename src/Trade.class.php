<?php

class Trade
{
	private $my_items;
	private $their_items;
	private $expected_items;
	private $giving_items;
	private $last_action;
	private $eobot;
	private $partner;
	private $state;
	
	function __construct(&$eobot, $partner)
	{
		$this->state = Protocol::TRADE_REQUESTED;
		$this->eobot = $eobot;
		$this->partner = $partner;
		$this->my_items = array();
		$this->their_items = array();
		$this->expected_items = array();
		$this->giving_items = array($this->eobot->GetItemByName("eon")->id => 1);
		$this->last_action = time();
	}
	
	public function GetPartner() { return $this->partner; }
	
	public function GetState() { return $this->state; }
	
	public function SetState($new_state) { $this->state = $new_state; }
	
	public function ResetTimer() { $this->last_action = time(); }
	
	public function Process()
	{
		// time out
		if(time() > $this->last_action + $this->eobot->config->get("Game", "TradeTimeout"))
		{
			$this->eobot->CancelTrade();
			return;
		}
		
		// check if other player is still near us
		/*$nearby = $this->eobot->GetNearbyCharacter($partner->id);
		if($nearby === null)
		{
			$this->eobot->CancelTrade();
			return;
		}*/
		
		// check if we're waiting for other player to accept our trade request
		if($this->state <= Protocol::TRADE_REQUESTED)
			return;
		
		// check if we have added all the items we want to give
		foreach($this->giving_items as $key => $val)
		{
			if(isset($this->my_items[$key]))
			{
				continue;
			}
			if(time() > $this->last_action + $this->eobot->config->get("Game", "TradeAddItemDelay"))
			{
				// add missing item to trade
				$amount = min($this->eobot->me->inventory->GetAmount($key), $val);
				Console::Log("Amount: ". $amount);
				
				if($amount <= 0)
				{
					unset($this->giving_items[$key]);
				}
				else
				{
					$this->giving_items[$key] = $amount;
					$this->my_items[$key] = $amount;
					$this->eobot->TradeAddItem($key, $amount);						
				}
				
			}
			return;
		}
		
		// check if we have anything to add, if not we need to close trade
		if(count($this->giving_items) == 0)
		{
			$this->eobot->CancelTrade();
			return;
		}
		
		// check if partner has given all the items we expect
		foreach($this->expected_items as $key => $val)
		{
			if(isset($this->their_items[$key]))
			{
				continue;
			}
			// they are missing an item
			return;
		}
		
		// check if we have agreed
		if($this->state != Protocol::TRADE_ACCEPTED)
		{
			if(time() > $this->last_action + $this->eobot->config->get("Game", "TradeConfirmDelay"))
			{
				// make sure other player has added at least 1 item
				if(count($this->their_items) > 0)
					$this->eobot->AcceptTrade();				
			}
			return;
		}
		
		// everything is ok, waiting on other player to accept
	}
	
	public function SetExpectedItems($items)
	{
		$this->expected_items = $items;
	}
	
	public function SetGivingItems($items)
	{
		$this->giving_items = $items;
	}
	
	public function TradeChanged($mine, $theirs)
	{
		$this->ResetTimer();
		$this->state = Protocol::TRADE_TRADING;
		$this->my_items = $mine;
		$this->their_items = $theirs;
	}
}
