# PiastrixClient

## Подготовка

Для работы с библиотекой **Piastrixlib** необходимо установить PHP >= 7.2. 

## Быстрый старт

Для примера выставим счет для других валют - invoice. Убедитесь, что Ваш магазин активирован и настроен для работы
по API: получен ID магазина, указан URL взаимодействия, сгенерирован секретный ключ и указаны IP адреса ваших сервисов.
Payway платежных направлений для выставления счетов Вы можете найти в личном кабинете в настройках магазина в разделе
«Методы оплаты».

### Выставиление счета для других валют - invoice

Чтобы приступить к работе с библиотекой, необходимо подключить piastrixlib:

```php
include 'piastrixlib.php';
```
Определите параметры: secret_key - секретный ключ магазина(из настроек магазина) и shop_id - ID вашего магазина.

```php
$secret_key = 'YourSecretKey';
$shop_id = 'YourShopID';
```

Создаем объект библиотеки:

```php
$piastrix = new PiastrixClient($shop_id, $secret_key);
```

Сформируйте необходимые данные по invoice. Пример:
```php
$amount = '1000';
$currency = '840';
$payway = 'card_rub';
$shop_order_id = '101';
```

В запросе могут передаваться дополнительные параметры, например, description – описание счета, или phone – номер телефона, для оплаты через мобильную коммерцию и другие (failed_url, success_url, callback_url). Их формируем в отдельный массив extra_fields, добавляя при вызове метода, если необходимо(по умолчанию $extra_fields=NULL):

```php
$extra_fields = ['description' => 'Test description'];
$piastrix->invoice($amount, $currency, $shop_order_id, $payway, $extra_fields);
```

Пример ответа:

```php

{'data': {'lang': 'ru', 'm_curorderid': '102473088', 'm_historyid': '857085113', 'm_historytm': '1568119260', 'referer': 'https://payeer.com/merchant/?m_historyid=857085113&m_historytm=1568119260&m_curorderid=102473088&lang=ru'}, 'id': 94869365, 'method': 'GET', 'url': 'https://payeer.com/api/merchant/process.php'
```

В этом примере мы выставили счет, вызвав метод *invoice* из библиотеки **Piastrixlib** и передали необходиммые для этого
данные. Помните, что для создание реального invoice нужно использовать идентификатор и секретный ключ настоящего магазина.

Детальней ознакомиться с другими методами и работой с Piastrix API можно [тут](https://piastrix.docs.apiary.io/#introduction/ptx-api).