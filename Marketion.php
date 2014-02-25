<?php

/**
 * Class Marketion
 */
class Marketion extends CApplicationComponent
{

    static $api_url = 'http://www.marketion.ru/app/api.php';

    private $login;
    private $password;
    private $sessid;
    private $response_format = 'JSON';
    private $in_debug = false;
    private $need_jsonp = false;

    /**
     * @throws CException
     */
    public function init()
    {
        if (!function_exists('curl_init')) {
            throw new CException ('Для работы расширения требуется cURL');
        }

        parent::init();
    }

    /**
     * Авторизация.
     *
     * @throws CException
     */
    public function auth()
    {
        $result = $this->query('User.Login', ['Username' => $this->login, 'Password' => $this->password], false);
        if ($result) {
            $this->sessid = $result->SessionID;
        } else {
            throw new CException ('Невозможно авторизоваться. Проверьте правильность логина и пароля.');
        }
        return $result ? true : false;
    }

    /**
     * @param string $command
     * @param array $arguments
     * @param bool $need_session
     * @return bool|mixed|SimpleXMLElement
     * @throws CException
     */
    public function query($command = '', $arguments = [], $need_session = true)
    {
        $url = self::$api_url;
        $arguments['command'] = $command;
        if ($need_session) {
            if (!$this->sessid) {
                $this->auth();
            }
            $arguments['SessionID'] = $this->sessid;
        }
        $arguments['ResponseFormat'] = $this->response_format;
        if ($this->need_jsonp) {
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
        if (!$result) {
            return false;
        } else {
            if ($result->Success != 1 && $this->in_debug) {
                throw new CException ('Error in: ' . $command . '<br /> Error code: ' . print_r($result->ErrorCode, true) . '<br />' . 'Raw error text: ' . $content);
            } else {
                return $result;
            }
        }
    }

    /**
     * @param $url
     * @param array $arguments
     * @param bool $need_result
     * @return mixed
     */
    private function get_content($url, $arguments = [], $need_result = true)
    {
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
     * Импортирует данные о подписчике в список подписчиков.
     *
     * @param integer $listId
     * @param string $emailList
     * @throws CException
     * @return bool
     */
    public function subscribersImport($listId, $emailList)
    {
        $command = 'Subscribers.Import';
        $response = $this->query($command, array(
            'ListID' => $listId,
            'ImportStep' => 1,
            'ImportType' => 'Copy',
            'ImportData' => $emailList,
        ));
        if (!$response || $response->Success != 1) {
            throw new CException ('Error while processing: ' . $command);
        }
        $params = [
            'ListID' => $listId,
            'ImportStep' => 2,
            'ImportID' => $response->ImportID,
        ];
        $this->query($command, $params);
    }

    /**
     * Создаёт новый список подписчиков.
     *
     * @param string $listName
     * @throws CException
     * @return bool
     */
    public function listCreate($listName)
    {
        $response = $this->query('List.Create', [
            'SubscriberListName' => $listName,
        ]);
        if (!$response || $response->Success != 1) {
            throw new CException ('Error while processing: ' . 'List.Create');
        }

        return $response->ListID;
    }

    /**
     * Отображает списки подписчиков, созданные вошедшим в систему пользователем.
     *
     * @throws CException
     * @return bool
     */
    public function listsGet()
    {
        $response = $this->query('Lists.Get', [
            'OrderField' => 'name', // {field name of subscriber list} (required)
            'OrderType' => 'ASC' // | DESC} (required)
        ]);
        if (!$response || $response->Success != 1) {
            throw new CException ('Error while processing: ' . 'Lists.Get');
        }

        return [
            'TotalListCount' => $response->TotalListCount,
            'Lists' => $response->Lists
        ];
    }

    /**
     * Отображает список подписчиков.
     *
     * @param $listId
     * @throws CException
     * @return array|bool
     */
    public function listGet($listId)
    {
        $response = $this->query('List.Get', [
            'ListID' => $listId,
        ]);
        if (!$response || $response->Success != 1) {
            throw new CException ('Error while processing: ' . 'List.Get');
        }
        return $response->List;
    }

    /**
     * Отображает списки подписчиков, в которые включен вошедший в систему пользователь.
     *
     * @throws CException
     * @return bool
     */
    public function subscriberGetLists()
    {
        $response = $this->query('Subscriber.GetLists', []);
        if (!$response || $response->Success != 1) {
            throw new CException ('Error while processing: ' . 'Subscriber.GetLists');
        }

        return $response->SubscribedLists;
    }

    /**
     * Вносит e-mail адрес в один или несколько списков подписчиков.
     *
     * @param $listId
     * @param $email
     * @param string $ip
     * @throws CException
     * @return mixed
     */
    public function subscriberSubscribe($listId, $email, $ip = '8.8.8.8')
    {
        $response = $this->query('Subscriber.Subscribe', [
            'ListID' => $listId,
            'EmailAddress' => $email,
            'IPAddress' => $ip
        ]);
        if (!$response || $response->Success != 1) {
            throw new CException ('Error while processing: ' . 'Subscriber.Subscribe');
        }

        return $response->SubscriberID;
    }

    /**
     * Создаёт пустую запись e-mail адреса пользователя.
     * Редакрирует информацию о настраиваемом поле.
     *
     * @param $fromEmail
     * @param $fromName
     * @param $subject
     * @param $bodyHtml
     * @param string $bodyText
     * @throws CException
     * @return mixed
     */
    public function emailCreate($fromEmail, $fromName, $subject, $bodyHtml, $bodyText = '')
    {
        $response = $this->query('Email.Create');
        if (!$response || $response->Success != 1) {
            throw new CException ('Не удалось создать сообщение. Error while processing: ' . 'Email.Create');
        }
        $emailId = $response->EmailID;

        $response = $this->query('Email.Update', [
            'ValidateScope' => 'Campaign',
            'EmailID' => $emailId,
            'FromName' => $fromName,
            'FromEmail' => $fromEmail,
            'Mode' => 'Empty',
            'Subject' => $subject,
            'PlainContent' => urlencode($bodyText),
            'HTMLContent' => urlencode($bodyHtml)
        ]);
        if (!$response || $response->Success != 1) {
            throw new CException ('Не удалось обновить письмо. Error while processing: ' . 'Email.Update');
        }

        return $emailId;
    }

    /**
     * Создает новую кампанию для рассылки.
     * Редактирует настройки кампании.
     *
     * @param $campaignName
     * @param $emailId
     * @param $listId
     * @throws CException
     * @return mixed
     */
    public function campaignCreate($campaignName, $emailId, $listId)
    {
        $response = $this->query('Campaign.Create', ['CampaignName' => $campaignName]);
        if (!$response || $response->Success != 1) {
            throw new CException ('Не удалось создать кампанию. Error while processing: ' . 'Campaign.Create');
        }
        $campaignId = $response->CampaignID;

        $response = $this->query('Campaign.Update', [
            'CampaignID' => $campaignId,
            'RelEmailID' => $emailId,
            'ScheduleType' => 'Immediate',
            'RecipientListsAndSegments' => $listId . '::0'
        ]);
        if (!$response || $response->Success != 1) {
            throw new CException ('Не удалось обновить кампанию. Error while processing: ' . 'Campaign.Update');
        }

        return $campaignId;
    }

    /**
     * Отображает пользователей, включенных в определённый список подписчиков.
     *
     * @param $listId
     * @throws CException
     * @return bool
     */
    public function subscribersGet($listId)
    {
        $response = $this->query('Subscribers.Get', [
            'OrderField' => 'name',
            'OrderType' => 'ASC',
            'RecordsFrom' => 0,
            'RecordsPerRequest' => 1000,
            'SearchField' => '',
            'SearchKeyword' => '',
            'SubscriberListID' => $listId,
            'SubscriberSegment' => 'Active'
        ]);
        if (!$response || $response->Success != 1) {
            throw new CException ('Не удалось получить список подписчиков. Error while processing: ' . 'Subscribers.Get');
        }

        return [
            'Subscribers' => $response->Subscribers,
            'TotalSubscribers' => $response->TotalSubscribers
        ];
    }

    /**
     * Удаляет аккаунты подписчиков.
     *
     * @param int $listId
     * @param array $subscribers
     * @param bool $s_is_array
     * @throws CException
     * @return bool
     */
    public function subscribersDelete($listId, $subscribers, $s_is_array = true)
    {
        if ($s_is_array) {
            $subs = implode(',', $subscribers);
        } else {
            $subs = [];
            foreach ($subscribers as $k => $item) {
                $subs[] = $item->SubscriberID;
            }
            $subs = implode(',', $subs);
        }
        $response = $this->query('Subscribers.Delete', [
            'SubscriberListID' => $listId,
            'Subscribers' => $subs
        ]);
        if (!$response || $response->Success != 1) {
            throw new CException ('Не удалось удалить подписчиков. Error while processing: ' . 'Subscribers.Delete');
        }

        return true;
    }

    /**
     * Исключает подписчика из данного списка.
     *
     * @param int $listId
     * @param $email
     * @throws CException
     * @internal param array $subscribers
     * @return bool
     */
    public function subscriberUnsubscribe($listId, $email)
    {
        $response = $this->query('Subscriber.Unsubscribe', [
            'ListID' => $listId,
            'EmailAddress' => $email,
            'IPAddress' => '0.0.0.0',
            'Preview' => 0
        ]);
        if (!$response || $response->Success != 1) {
            throw new CException ('Не удалось удалить подписчика из списка. Error while processing: ' . 'Subscriber.Unsubscribe');
        }

        return true;
    }
}
