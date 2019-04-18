# PHPPushNotificationClient
Push Notification is a notification that is forcibly sent by the server to the client so that the notification sent to the client without waiting for the client to request it. In order for the notification to be accepted by the client, the client and server must always be connected through socket communication.

PHP can receive notification sent by notification server. 

## Requirement

This library need Thread class. In case it not installed on your server, you can download it from https://windows.php.net/downloads/pecl/releases/pthreads/2.0.10/

See https://secure.php.net/manual/en/pthreads.installation.php to install

## Push Notification Server

To get push notification server, please click https://github.com/kamshory/PushNotificationServer

To get push notification sender, please click https://github.com/kamshory/PushNotificationSender


**Notification Flow**
1. Every device that will receive notifications must register the device ID either manually by the user or automatically when the application is first run.
2. Each notification will be sent to the device according to the destination device ID that has been registered previously according to the API key of the notification.
3. The application server cannot send notifications to devices that have not been registered according to the API key of the application.
4. If two or more applications have the same API key and the same device ID is connected to the server when the server sends a notification, the two applications will get the same notification. Notifications will be marked as "sent" after the notification has been successfully sent to the first application.
5. If the connection between the application and the server is disconnected and realized by the server, then the server will discard the connection from the list and if there is no application connected to the server for notifications sent, then the server only stores notifications without sending notifications to the receiving device and will not mark notifications as "sent".
6. If an error occurs when sending a notification, the server will disconnect and discard the connection but will not mark the notification as "sent" if no application actually receives the notification, then the server only stores notifications without sending notifications to the receiving device and will not mark notifications as "sent".
7. When the connection is lost, either forced by the server or due to an error, the application will make a new connection to the server.
8. When the application is connected to the server, the server will checks whether there are notifications that have not been sent or not and whether there is a history of deletion notifications that have not been sent or not. If there is a notification that has not been sent, the server will send the notification and will mark the notification as: "sent". If there is a history of deletion of notifications that have not been sent, the server will send a history of deletion of the notification and will mark the deletion history of the notification as: "sent".

To get push notification, developers can create their own program code and can also use the Application Programming Interface (API) provided by third parties. If the developer wants to build their own notification server, the following API can be used to get push notifications by integrating it with a mobile application.

## Security

Server will check each connection to server with two step authentication.

**Two Steps Authentication**
1. Client send request connection to server with API key, password and device ID (Step 1)
2. Server send question to client
3. Client send answer to server (Step 2)
4. Server check the answer
5. If answer is valid, server send token to client
6. If answer is invalid, server close the connection

After server validate the client, server will evaluate the client every 60 minutes. Client will receive new token. The purpose of the evaluation is to check whether the client is still connected to the server or not.

**Evaluation**
1. Server send question to client
2. Client send answer to server
3. Server check the answer
4. If answer is valid, server send new token to client
5. If answer is invalid, server close the connection

## Using Push Notification Client API

**Windows**

C:\xampp\php\php.exe -q C:\xampp\htdocs\PHPPushNotificationClient\ClientNotif.php


**Linux**

php -q /var/PHPPushNotificationClient/ClientNotif.php

## Example 

```php
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
```
