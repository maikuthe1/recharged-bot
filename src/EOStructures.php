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

/* TODO: refactor all this */
class EOCharacterPreview
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

class EOItem
{
	public $name;
	public $id;
}

class EOCharacter {
	const DIRECTION_MASK = 0x03;     // 0b00000011
	const SITTING_MASK = 0x10;       // 0b00010000
	const CHAIR_SITTING_MASK = 0x20; // 0b00100000
	const HIDDEN_MASK = 0x40;        // 0b01000000
	const GENDER_MASK = 0x80;       // 0b10000000
	const SKIN_MASK = 0x0F;         // 0b00001111
	
    public $name;
    public $id;
    public $map_id;
    public $map_x;
    public $map_y;
    public $direction;
    public $guild;
    public $level;
    public $gender;
    public $hairstyle;
    public $haircolor;
    public $race;
    public $maxhp;
    public $hp;
    public $maxtp;
    public $tp;
    public $admin;
    public $paperdoll;
    public $inventory;
	public $sitting; // 0 false, 1 ground, 2 chair
	public $hidden;


    function __construct() {
        $this->paperdoll = new EOPaperdoll();
        $this->inventory = new EOInventory();
		$this->sitting = 0;
    }
}

class EOInventory {
    private $items;

    function __construct() {
        $this->items = array();
    }

    public function Add($item, $amount) {
		$id = $item->id;
        if (isset($this->items[$id]))
		{
            $this->items[$id]["amount"] += $amount;
        }
		else
		{
			$this->items[$id]["data"] = $item;
            $this->items[$id]["amount"] = $amount;
        }
    }
	
	public function Remove($item, $amount) {
		$id = $item->id;
        if (isset($this->items[$id]))
		{
            $this->items[$id]["amount"] -= $amount;
			if($this->items[$id]["amount"] <= 0)
				unset($this->items[$id]);
        }
    }

    public function GetAmount($id) {
        return $this->items[$id]["amount"] ?? 0;
    }
}

class EOPaperdoll
{
    public $boots;
    public $gloves;
    public $belt;
    public $armor;
    public $necklace;
    public $hat;
    public $shield;
    public $weapon;
    public $ring1;
    public $ring2;
    public $armlet1;
    public $armlet2;
    public $bracer1;
    public $bracer2;

    function __construct($boots = 0, $gloves = 0, $belt = 0, $armor = 0, $necklace = 0, $hat = 0, $shield = 0, $weapon = 0, $ring1 = 0, $ring2 = 0, $armlet1 = 0, $armlet2 = 0, $bracer1 = 0, $bracer2 = 0)
    {
        $this->boots = $boots;
        $this->gloves = $gloves;
        $this->belt = $belt;
        $this->armor = $armor;
        $this->necklace = $necklace;
        $this->hat = $hat;
        $this->shield = $shield;
        $this->weapon = $weapon;
        $this->ring1 = $ring1;
        $this->ring2 = $ring2;
        $this->armlet1 = $armlet1;
        $this->armlet2 = $armlet1;
        $this->bracer1 = $bracer1;
        $this->bracer2 = $bracer2;
    }
}

class GatherNode
{
	public $id;
	public $amount = 0;
	public $resource_type;
	public $map_x;
	public $map_y;
}

class EOMap
{
	public $id;
	public $data;
	public $gather_nodes;
	
	function __construct($id)
	{
		if(file_exists("data/maps/" . $id .".json"))
		{
			$jsonString = file_get_contents("data/maps/" . $id .".json");
			$map_data = json_decode($jsonString, true);
			$this->gather_nodes = array();
			
			foreach($map_data["resources"] as $res_id => $res_data)
			{
				$node = new GatherNode();
				$node->id = $res_id;
				$node->resource_type = $res_data["res_type"];
				$node->map_x = $res_data["x"];
				$node->map_y = $res_data["y"];
				
				$this->gather_nodes[$res_id] = $node;
			}
			
			Console::Log("Loaded map " . $id);
			return $this;
		}
		else
		{
			Console::Log("Couldn't load map " . $id, "warning");
			return null;
		}
	}
}