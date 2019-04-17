<?php
class SSLPushClient
{
	public $host = "127.0.0.1";
	public $port = 80;
	public $socket = NULL;
	public $ssl = FALSE;
	public $timeout = 10;
	public $connected = FALSE;
	public $verifyPeer = FALSE;
	public $verifyPeerName = FALSE;
	public $apiKey = '';
	public $groupKey = '';
	public $deviceID = '';
	public $password = '';
	
	/**
	You need to override these methods
	*/	
	public $onMessageReceived = NULL;
	public $onConnected = NULL;
	public $onDisconnected = NULL;
	

	public function __construct($host, $port, $ssl = FALSE)
	{
		$this->host = $host;
		$this->port = $port;
		$this->ssl = $ssl;
	}
	public function connect()
	{
		if($this->ssl)
		{		
			$context = stream_context_create(
				array(
				'ssl' => array(
					'verify_peer' => $this->verifyPeer,
					'verify_peer_name' => $this->verifyPeerName
				)
			));
	
			$hostname = "ssl://".$this->host.":".$this->port;
			if($this->socket = @stream_socket_client($hostname, $errno, $errstr, $this->timeout, STREAM_CLIENT_CONNECT, $context))
			{
				$this->connected = true;
			}
			else
			{
				$this->connected = FALSE;
			}
		}
		else
		{
			$this->socket = @socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
			$result = @socket_connect($this->socket, $this->host, $this->port);
			if($result)
			{
				$this->connected = true;
			}
			else
			{
				$this->connected = FALSE;
			}
		}
		if($this->connected)
		{
			$this->_callConnected();
		}
	}
	public function login($apiKey, $groupKey, $deviceID, $password)
	{
		$this->apiKey = $apiKey;
		$this->groupKey = $groupKey;
		$this->deviceID = $deviceID;
		$this->password = $password;

		$payload = array(
			'deviceID'=>$deviceID
		);
		$raw = json_encode($payload, JSON_PRETTY_PRINT);
		$headers = array();
		
		$unixTime = time(0);
		
		$token = sha1($unixTime.$apiKey);
		$hash = sha1(sha1($password)."-".$token."-".$apiKey);
		
		$authentication = "key=".$apiKey."&token=".$token."&hash=".$hash."&time=".$unixTime."&group=".$groupKey;
		$headers[] = 'Command: login';
		$headers[] = 'Authorization: '.$authentication;
		$headers[] = 'Content-type: application/json';
		$data = $this->_buildData($raw, $headers);
		$this->_sendData($data);
		return $this;
	}
	public function getCommand($header)
	{
		$lines = explode("\r\n", $header);
		$command = '';
		foreach($lines as $key=>$line)
		{
			if(stripos($line, "Command:") !== FALSE)
			{
				$arr = explode(":", $line);
				$str = trim($arr[1]);
				$command = $str;
			}
		}
		return $command;
	}
	private function _sendData($data)
	{
		if($this->_write($this->socket, $data, strlen($data)) === FALSE)
		{
			$this->_callDisconnected();
		}
	}
	private function _buildData($raw, $headers = array())
	{
		if(!isset($headers))
		{
			$headers = array();
		}
		if(!is_array($headers))
		{
			$headers = array();
		}
		$headers[] = 'Content-length: '.strlen($raw);
		return implode("\r\n", $headers)."\r\n\r\n".$raw;
	}
	private function _getConetntLength($header)
	{
		$lines = explode("\r\n", $header);
		$length = 0;
		foreach($lines as $key=>$line)
		{
			if(stripos($line, "Content-length:") !== FALSE)
			{
				$arr = explode(":", $line);
				$str = trim($arr[1]);
				$length = $str * 1;
			}
		}
		return $length;
	}
	private function _read($file, $length)
	{
		$byte = FALSE;
		if($this->ssl)
		{
			$byte = @fread($file, $length);
		}
		else
		{
			$byte = @socket_read($file, $length);
		}
		if($byte === FALSE || $byte === "" || $byte === NULL)
		{
			$this->_callDisconnected();
		}
		return $byte;
	}
	private function _write($file, $data, $length)
	{
		if($this->ssl)
		{
			return @fwrite($file, $data, $length);
		}
		else
		{
			return @socket_write($file, $data, $length);
		}
	}
	private function _callDisconnected()
	{
		if(is_callable($this->onDisconnected))
		{
			call_user_func($this->onDisconnected); 
		}
		$this->connected = FALSE;
	}
	private function _callConnected()
	{
		if(is_callable($this->onConnected))
		{
			call_user_func($this->onConnected); 
		}
	}
	public function start()
	{
		while($this->connected)
		{
			$header = "";
			$out = FALSE;
			do
			{
				$out = $this->_read($this->socket, 1);
				if($out === FALSE || $out === "" || $out === NULL)
				{
					break;
				}
				$header .= $out;
			}
			while(stripos($header, "\r\n\r\n") === FALSE);
			if($out === FALSE)
			{
				$this->_callDisconnected();
			}
			$contentLength = $this->_getConetntLength($header);
			if($contentLength > 0)
			{
				$body = $this->_read($this->socket, $contentLength);
				$command = $this->getCommand($header);
				if($command == 'question')
				{
					$this->_answerQuestion($header, $body);
				}
				else
				{
					call_user_func($this->onMessageReceived, $command, $header, $body); 
				}
			}
		}
		$this->_callDisconnected();
	}
	private function _answerQuestion($header, $body)
	{
		$data = json_decode($body, true);
		$question = $data['question'];
		$deviceID = $data['deviceID'];
		$answer = sha1(sha1($this->password)."-".$question."-".$deviceID);
		$headers[] = 'Command: answer';
		$headers[] = 'Content-type: application/json';
		$raw = json_encode(array(
			'question'=>$question,
			'answer'=>$answer
		));
		$data = $this->_buildData($raw, $headers);
		$this->_sendData($data);
	}
}
?>