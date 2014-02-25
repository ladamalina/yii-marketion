Yii компонент для работы с api сервиса e-mail рассылок [Marketion](http://marketion.ru)

## Установка

Загрузите yii-marketion из этого репозитория github:

    cd protected/components
    git clone https://github.com/ladamalina/yii-marketion.git

В protected/config/main.php внесите следующие строки:

```php
'components' => [
    'marketion' => [
        'class'    => 'application.components.yii-marketion.Marketion',
        'login'     => '', // логин клиента
        'password'   => '', // пароль
        'in_debug' => false,
    ],
]
```

## Использование

Создание нового списка адресатов для рассылки

```php
$listId = Yii::app()->marketion->listCreate('Дайджест "Академия шарма"');
// возвращает ID вновь созданного списка
```

Получаем все наши списки адресатов

```php
list($TotalListCount, $Lists) = Yii::app()->marketion->listsGet();
```

Вносим e-mail адрес в список

```php
$SubscriberID = Yii::app()->marketion->subscriberSubscribe(
    $listId,
    $email,
    $ip
);
```

Импорт нескольких адресов в список

```php
Yii::app()->marketion->subscribersImport(
    $listId,
    'email1@example.com,email2@example.com,email3@example.com'
);
```

Запрашиваем пользователей, включенных в определённый список (лимит 1000)

```php
list($Subscribers, $TotalSubscribers) = Yii::app()->marketion->subscribersGet($listId);
```

Исключаем подписчика из данного списка

```php
Yii::app()->marketion->subscriberUnsubscribe($listId, $email);
```

## Документация
Все обращения к API Маркетиона делаются однотипно, дополнительные функции легко добавить, если вам необходимо, например, управление кампаниями, шаблонами и запуск рассылок из своего приложения. В файле API.html находится документация по всем доступным методам.

## Особенности сервиса
Печальная особенность Маркетиона в том, что он не может обработать за один раз большую партию данных. Импорт огромных списков адресов я рекомендую разбивать на группы по 200-500 имейлов за один запрос.
