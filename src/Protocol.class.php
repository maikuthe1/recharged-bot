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
class Protocol {
    const COMMA = "\xFF";
    const NULL = "\xFE";

    const F = array(
        "Connection" => 1,
        "Message" => 2,
        "Login" => 3,
        "Account" => 4,
        "Welcome" => 5,
        "Character" => 6,
		// 7 ??
		"Refresh" => 8, //????? maybe
        "Walk" => 9,
        "Face" => 10,
		"Chair" => 11,
        "Emote" => 12,
		// 13 ??
        "Attack" => 14,
        "Spell" => 15,
		"Shop" => 16,
        "Item" => 17,
        "StatSkill" => 18,
		// 19 ??
        "Global" => 20,
		"Talk" => 21,
        "Warp" => 23,
        "Channel" => 24,
        "Jukebox" => 25,
        "Players" => 26,
        "Avatar" => 27,
        "Party" => 28,
        "NPCAutoRefresh" => 29,
        "NPC" => 29,
        "PlayerRange" => 30,
        "NPCRange" => 31,
        "Range" => 32,
        "Paperdoll" => 33,
        "Effect" => 34,
        "Trade" => 35,
        "Chest" => 36,
        "Door" => 37,
        "Bank" => 38,
        "Locker" => 39,
		"Barber" => 40,
        "Guild" => 41,
        "Music" => 42,
        "Sit" => 43,
		"Recover" => 44,
        "Board" => 45,
        "Cast" => 46,
        "Arena" => 47,
        "Priest" => 48,
        "Marriage" => 49,
        "AdminInteract" => 50,
        "Citizen" => 51,
		"Quest" => 52,
		"MagicAttack" => 53,
		"Boss" => 54,
		"Gather" => 55,
		"Charger" => 56,
		"Captcha" => 249,
        "Init" => 255
    );

    
    const A = array(
        "Request" => 1,
        "Two" => 2,
        "Accept" => 3,
        "Four" => 4,
        "Reply" => 5,
        "Remove" => 6,
        "Agree" => 7,
        "Eight" => 8,
        "Nine" => 9,
        "Ten" => 10,
        "Create" => 11,
        "Add" => 12,
        "Player" => 13,
        "Take" => 14,
        "Use" => 15,
        "Buy" => 16,
        "Sell" => 17,
        "Open" => 18,
        "Close" => 19,
        "Switch" => 20,
		"Msg" => 21,
        "Spec" => 22,
        "Admin" => 23,
        "List" => 24,
        "Server" => 25,
        "Tell" => 26,
        "Report" => 27,
		"Announce" => 28,
        "Server" => 29,
        "Drop" => 30,
		"Junk" => 31,
		"Obtain" => 32,
        "Time" => 33,
		"Spawn" => 34,
        "Swap" => 35,
		"Get" => 36,
		"Kick" => 37,
		"Rank" => 38,
		"TargetSelf" => 39,
		"TargetOther" => 40,
		"TargetGroup" => 41,
		"Dialog" => 42,
		"Dialog" => 43,
		"Config" => 230,
		"Ping" => 231,
		"Pong" => 232,
		"Net242" => 233,
		"Net243" => 234,
		"Net244" => 235,
		"Net245" => 236,
        "Ping_Old" => 240,
        "Pong_old" => 241,
        "Net3" => 242,
		"Error" => 250,
        "Init" => 255
    );

    const INIT_OUTDATED = 0;
    const INIT_OK = 1;
    const INIT_BANNED = 2;

    const LOGIN_OK = 1;
    const LOGIN_WRONG_USER = 2;
    const LOGIN_WRONG_USERPASS = 3;
    const LOGIN_BANNED = 4;
    const LOGIN_LOGGED_IN = 5;
    const LOGIN_SERVER_BUSY = 6;

    const DIRECTION_DOWN = 0;
    const DIRECTION_LEFT = 1;
    const DIRECTION_UP = 2;
    const DIRECTION_RIGHT = 3;

    const EMOTE_HAPPY = 1;
    const EMOTE_DEPRESSED = 2;
    const EMOTE_SAD = 3;
    const EMOTE_ANGRY = 4;
    const EMOTE_CONFUSED = 5;
    const EMOTE_SURPRISED = 6;
    const EMOTE_HEARTS = 7;
    const EMOTE_MOON = 8;
    const EMOTE_SUICIDAL = 9;
    const EMOTE_EMBARASSED = 10;
    const EMOTE_DRUNK = 11;
    const EMOTE_TRADE = 12;
    const EMOTE_LEVELUP = 13;
    const EMOTE_PLAYFUL = 14;

    public static function DecodeInteger($bytes) {
        if (strlen($bytes) > 5) {
            return -1;
        }

        $b1 = ord($bytes[0] ?? Protocol::NULL);
        $b2 = ord($bytes[1] ?? Protocol::NULL);
        $b3 = ord($bytes[2] ?? Protocol::NULL);
        $b4 = ord($bytes[3] ?? Protocol::NULL);
        $b5 = ord($bytes[4] ?? Protocol::NULL);

        if ($b1 == 254) $b1 = 1;
        if ($b2 == 254) $b2 = 1;
        if ($b3 == 254) $b3 = 1;
        if ($b4 == 254) $b4 = 1;
        if ($b5 == 254) $b5 = 1;

        if ($b1 == 0) $b1 = 128;
        if ($b2 == 0) $b2 = 128;
        if ($b3 == 0) $b3 = 128;
        if ($b4 == 0) $b4 = 128;
        if ($b5 == 0) $b5 = 128;

        --$b1;
        --$b2;
        --$b3;
        --$b4;
        --$b5;

        return ($b5 * 4097152081 + $b4 * 16194277 + $b3 * 64009 + $b2 * 253 + $b1);
    }

    public static function EncodeInteger($number, $size = 1) {
        $bytes = '';

        for($i = 1; $i <= $size; $i++) {
            $bytes .= Protocol::NULL;
        }

        $onumber = $number;
		
		if ($onumber >= 4097152081) {
            $bytes[4] = chr($number / 4097152081 + 1);
            $number = $number % 4097152081;
        }

        if ($onumber >= 16194277) {
            $bytes[3] = chr($number / 16194277 + 1);
            $number = $number % 16194277;
        }

        if ($onumber >= 64009) {
            $bytes[2] = chr($number / 64009 + 1);
            $number = $number % 64009;
        }

        if ($onumber >= 253) {
            $bytes[1] = chr($number / 253 + 1);
            $number = $number % 253;
        }

        $bytes[0] = chr($number + 1);

        return $bytes;
    }

    public static function Dwind($str, $multi) {
        $newstr = '';
        $length = strlen($str);

        $buffer = '';

        if ($multi == 0) return $str;

        for ($i = 0; $i < $length; ++$i) {
            $c = ord($str[$i]);

            if ($c % $multi == 0) {
                $buffer .= chr($c);
            } else {
                if (strlen($buffer) > 0) {
                    $newstr .= strrev($buffer);
                    $buffer = '';
                }

                $newstr .= chr($c);
            }
        }

        if (strlen($buffer) > 0) {
            $newstr .= strrev($buffer);
        }

        return $newstr;
    }

    public static function Encode($packet) {
        if (strlen($packet) < 1)
            return false;

        $packet = str_split($packet);
        foreach ($packet as &$char){
            $char = ord($char);
            $char -= 128;
            if ($char < 0)
                $char = 256 + $char;
        }
        $newpacket = array();
        $i = 0;
        $j = 0;
        while ($i < count($packet)){
            $newpacket[$i] = $packet[$j];
            $i += 2;
            $j++;
        }
        $i -= 3;
        if(!(count($packet) % 2))
            $i = count($packet)-1;
        while ($i > 0){
            $newpacket[$i] = $packet[$j];
            $i -= 2;
            $j++;
        }
        ksort($newpacket);
        foreach ($newpacket as &$char) {
            if ($char == 128) {
                $char = 0;
            } elseif ($char == 0) {
                $char = 128;
            }

            $char = chr($char);
        }

        return implode('',$newpacket);
    }

    public static function Decode($packet) {
        if (strlen($packet) < 1)
            return false;
        $packet = str_split($packet);
        foreach ($packet as &$char){
            $char = ord($char);
            $char -= 128;
            if ($char < 0)
                $char = 256 + $char;
        }
        $newpacket = array();
        $i = 0;
        while ($i < count($packet)){
            $newpacket[] = $packet[$i];
            $i += 2;
        }
        $i -= 1;
        if(count($packet) % 2)
            $i = count($packet)-2;
        while ($i > 0){
            $newpacket[] = $packet[$i];
            $i -= 2;
        }
        foreach ($newpacket as &$char) {
            if ($char == 128) {
                $char = 0;
            } elseif ($char == 0) {
                $char = 128;
            }

            $char = chr($char);
        }

        $finalpacket = implode('',$newpacket);

        return $finalpacket;
    }

    public static function timestamp() {
        return (date('H') * 3600) + (date('i') * 60) + (date('s') * 100) + (intval(explode(' ', microtime())[0] * 100));
    }
}
