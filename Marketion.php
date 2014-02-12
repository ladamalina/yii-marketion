<?php

class Marketion {
	static $api_url = 'http://www.marketion.ru/app/api.php';

	private $sessid;
	private $response_format;
	private $in_debug;
	private $need_jsonp;

	function __construct($login, $password, $response_format = 'JSON', $need_jsonp = false, $in_debug = false) {
		$this->response_format = $response_format;
		$this->in_debug = $in_debug;
		$this->need_jsonp = $need_jsonp;
		$result = $this->query('User.Login', array('Username' => $login, 'Password' => $password), 0);
		if($result) {
			$this->sessid = $result->SessionID;
		}
	}

	function query($command = '', $arguments = array(), $need_session = 1) {
		$url = self::$api_url;
		$arguments['command'] = $command;
		if($need_session) {
			$arguments['SessionID'] = $this->sessid;
		}
		$arguments['ResponseFormat'] = $this->response_format;
		if($this->need_jsonp) {
			$arguments['JSONPCallBack'] = 'true';
		}
		$content = $this->get_content($url, $arguments);

		switch ($this->response_format) {
			case 'JSON':
				$result = json_decode($content);
				break;
			case 'XML':
				$result = simplexml_load_string($content);
				break;
		}
		//print_r($content);die();
		if(!$result) {
			return false;
		} else {
			if ($result->Success != 1 && $this->in_debug) {
				die(' Error in: ' . $command . '<br /> Error code: ' . print_r($result->ErrorCode, true) . '<br />' . 'Raw error text: ' . $content);
			} else {
				return $result;
			}
		}
	}

	private function get_content($url, $arguments = array(), $need_result = 1) {
		$downloader = curl_init();
		curl_setopt($downloader, CURLOPT_URL, $url);
		curl_setopt($downloader, CURLOPT_POST, 1);
		$postdata = http_build_query($arguments, '', '&');
		curl_setopt($downloader, CURLOPT_POSTFIELDS, $postdata);
		curl_setopt($downloader, CURLOPT_HEADER, 0);
		curl_setopt($downloader, CURLOPT_TIMEOUT, 60);
		curl_setopt($downloader, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($downloader, CURLOPT_FOLLOWLOCATION, 1);

		if ($need_result) {
			$return = curl_exec($downloader);
		}
		curl_close($downloader);

		return $return;
	}

	/**
	 * @param integer $listId
	 * @param string $emailList
	 * @return bool
	 */
	public function subscribersImport($listId, $emailList, $emailOnly=true) {
		$response = $this->query('Subscribers.Import', array(
			'ListID' => $listId,
			'ImportStep' => 1,
			'ImportType' => 'Copy',
			'ImportData' => $emailList,
		));
		if (!$response || $response->Success != 1) {
			print_r($response);
			return false;
		}
		$params = array(
			'ListID' => $listId,
			'ImportStep' => 2,
			'ImportID' => $response->ImportID,
			'MappedFields' => array('FIELD1' => 'EmailAddress'),
		);
		if (!$emailOnly) {
		    $params['FieldTerminator'] = ';';
		    $params['MappedFields'] = array('FIELD1' => 'EmailAddress', 'FIELD2' => '1448');
		}
		$response = $this->query('Subscribers.Import', $params);
		return true;
	}

	/**
	 * @param string $listName
	 * @return bool
	 */
	public function listCreate($listName) {
		$response = $this->query('List.Create', array(
			'SubscriberListName' => $listName,
		));
		if (!$response || $response->Success != 1) {
			print_r($response);
			return false;
		}
		return $response->ListID;
	}

	/**
	 * @return bool
	 */
	public function listsGet() {
		$response = $this->query('Lists.Get', array(
			'OrderField' => 'name',// {field name of subscriber list} (required)
			'OrderType' => 'ASC'// | DESC} (required)
		));
		if (!$response || $response->Success != 1) {
			print_r($response);
			return false;
		}
		//print_r($response);
		return array('TotalListCount'=>$response->TotalListCount,'Lists'=>$response->Lists);
	}

	/**
	 * @param $listId
	 * @return array|bool
	 */
	public function listGet($listId) {
		$response = $this->query('List.Get', array(
			'ListID' => $listId
		,
		));
		if (!$response || $response->Success != 1) {
			print_r($response);
			return false;
		}
		return $response->List;
	}

	/**
	 * @return bool
	 */
	public function subscriberGetLists() {
		$response = $this->query('Subscriber.GetLists', array());
		if (!$response || $response->Success != 1) {
			print_r($response);
			return false;
		}
		//print_r($response);
		return $response->SubscribedLists;
	}

	/**
	 * @param $listId
	 * @param $email
	 * @param string $ip
	 * @return mixed
	 */
	public function subscriberSubscribe($listId, $email, $ip = '8.8.8.8') {
		$response = $this->query('Subscriber.Subscribe', array('ListID'=>$listId, 'EmailAddress'=>$email, 'IPAddress'=>$ip));
		if(!$response || $response->Success!=1) {
			print_r($response);
			die('Не удалось внести подписчика');
		}
		return $response->SubscriberID;
	}

	/**
	 * @param $fromEmail
	 * @param $fromName
	 * @param $subject
	 * @param $bodyHtml
	 * @param $bodyText
	 * @return mixed
	 */
	public function emailCreate($fromEmail, $fromName, $subject, $bodyHtml, $bodyText = '') {
		$response = $this->query('Email.Create');
		if(!$response || $response->Success!=1) {
			print_r($response);
			die('Не удалось создать сообщение');
		}
		$emailId = $response->EmailID;

		$response = $this->query('Email.Update', array(
			'ValidateScope'	=> 'Campaign',
			'EmailID'		=> $emailId,
			'FromName'		=> $fromName,
			'FromEmail'		=> $fromEmail,
			'Mode'			=> 'Empty',
			'Subject'		=> $subject,
			'PlainContent'	=> urlencode($bodyText),
			'HTMLContent'	=> urlencode($bodyHtml)
		));
		if(!$response || $response->Success!=1) {
			print_r($response);
			die('Не удалось обновить письмо');
		}
		return $emailId;
	}

	/**
	 * @param $campaignName
	 * @param $emailId
	 * @param $listId
	 * @return mixed
	 */
	public function campaignCreate($campaignName, $emailId, $listId) {
		$response = $this->query('Campaign.Create', array('CampaignName'=>$campaignName));
		if(!$response || $response->Success!=1) {
			print_r($response);
			die('Не удалось создать кампанию');
		}
		$campaignId = $response->CampaignID;

		$response = $this->query('Campaign.Update', array('CampaignID'=>$campaignId, 'RelEmailID'=>$emailId, 'ScheduleType'=>'Immediate', 'RecipientListsAndSegments'=>$listId.'::0'));
		if(!$response || $response->Success!=1) {
			print_r($response);
			die('Не удалось обновить кампанию');
		}
		return $campaignId;
	}

	/**
	 * @return bool
	 */
	public function subscribersGet($listId) {
		$response = $this->query('Subscribers.Get', array(
			'OrderField'		=> 'name',
			'OrderType'			=> 'ASC',
			'RecordsFrom'		=> 0,
			'RecordsPerRequest'	=> 1000,
			'SearchField'		=> '',
			'SearchKeyword'		=> '',
			'SubscriberListID'	=> $listId,
			'SubscriberSegment'	=> 'Active'
		));
		if(!$response || $response->Success!=1) {
			print_r($response);
			die('Не удалось внести подписчика');
		}
		return array('Subscribers'=>$response->Subscribers, 'TotalSubscribers'=>$response->TotalSubscribers);
	}

	/**
	 * @param int $listId
	 * @param array $subscribers
	 * @return bool
	 */
	public function subscribersDelete($listId, $subscribers, $s_is_array=true) {
	    if ($s_is_array) {
    	    $subs = implode(',',$subscribers);
	    } else {
    	    $subs = array();
    	    foreach ($subscribers as $k => $item) {
        	    $subs[] = $item->SubscriberID;
    	    }
    	    $subs = implode(',', $subs);
	    }
		$response = $this->query('Subscribers.Delete', array(
			'SubscriberListID'	=> $listId,
			'Subscribers'		=> $subs
		));
		if(!$response || $response->Success!=1) {
			print_r($response);
			die('Не удалось удалить подписчика');
		}
		return true;
	}

	/**
	 * @param int $listId
	 * @param array $subscribers
	 * @return bool
	 */
	public function subscriberUnsubscribe($listId, $email) {
	    $response = $this->query('Subscriber.Unsubscribe', array(
			'ListID' => $listId,
			'EmailAddress' => $email,
			'IPAddress' => '0.0.0.0',
			'Preview' => 0
		));
		return $response ? true : false;
	}
}
?>