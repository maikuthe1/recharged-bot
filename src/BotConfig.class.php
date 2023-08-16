<?php

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
	
	public function set($option, $value)
	{
		foreach($this->config as $section => $arr)
		{
			foreach($arr as $key => $val)
			if($key == $option)
			{
				$this->config[$section][$key] = $value;
			}
		}
	}
}