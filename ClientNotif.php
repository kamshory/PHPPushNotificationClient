<?php
require "lib/SSLPushClient.php";
require "lib/ProcessIndicator.php";

// usage
class ClientNotif
{
	public $apiKey = '';
	public $groupKey = '';
	public $deviceID = '';
	public $password = '';
	public $serverAddress = "localhost";
	public $serverPort = 9001;
	public $sslPushClient = NULL;
	public $waitReconnect = 5000000; // Micro second
	public $ssl = false;
	public function __construct($serverAddress, $serverPort, $ssl, $apiKey, $groupKey, $deviceID, $password)
	{
		$this->serverAddress = $serverAddress;
		$this->serverPort = $serverPort;
		$this->ssl = $ssl;
		$this->apiKey = $apiKey;
		$this->groupKey = $groupKey;
		$this->deviceID = $deviceID;
		$this->password = $password;
	}
	public function init()
	{
		$this->sslPushClient = new SSLPushClient($this->serverAddress, $this->serverPort, $this->ssl);
		// TODO Define you own function here...
		$this->sslPushClient->onMessageReceived = function($command, $header, $body)
		{
			// TODO Add your code here to receive the message
			$jsonObject = json_decode($body, TRUE);
			if($command == 'notification')
			{
				print_r($jsonObject);
			}
		};
		$this->sslPushClient->onConnected = function()
		{
			// TODO Add you code here when connected
			echo "CONNECTED...\r\n";
		};
		$this->sslPushClient->onDisconnected = function()
		{
			// TODO Add you code here when disconnected
			echo "DISCONNECTED...\r\n";
			$this->connected = false;
			$this->connect()->login()->start();
		};
		return $this;
	}
	public function connect()
	{
		$this->sslPushClient->connect();
		if($this->sslPushClient->connected)
		{
			$this->connected = true;
		}
		else
		{
			$this->connected = false;
			usleep($this->waitReconnect);
			$this->connect();
		}
		return $this;
	}
	public function login($apiKey = NULL, $groupKey = NULL, $deviceID = NULL, $password = NULL)
	{
		if($apiKey != NULL) $this->apiKey = $apiKey;
		if($groupKey != NULL) $this->groupKey = $groupKey;
		if($deviceID != NULL) $this->deviceID = $deviceID;
		if($password != NULL) $this->password = $password;
		$this->sslPushClient->login($this->apiKey, $this->groupKey, $this->deviceID, $this->password);
		return $this;
	}
	public function start()
	{
		$this->sslPushClient->start();
		return $this;
	}
}

$indicator = new ProcessIndicator("localhost", 96);
$indicator->start();
$api = new ClientNotif("localhost", 92, false, "PLANETBIRU", "1234567890W", "41fda1bcf6486301", "123456");
$api->init()->connect()->login()->start();
?>