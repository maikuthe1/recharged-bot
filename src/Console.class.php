<?php
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
            $logMessage = "\033[1;30m [".$timestamp . '] '. $resetColor . $colors[$logType] . $message . $resetColor . "\n\n";
        } else {
            // Default to white with [ ] for unknown log types
            $logMessage = '['.$timestamp . '] ' . $colors['info'] . $message . $resetColor . "\n\n";
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
