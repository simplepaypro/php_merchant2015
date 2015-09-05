<?php

require 'MySimplePayMerchant.class.php';

/**
 * Здесь представлен вариант с переадресацией сразу на страницу платежной системы
 * Установите payment_system = SP для переадресации на страницу выбора способа платежа
 * В случае если не будут указаны обязательные данные о плательщике, 
 * будет произведена переадресация на платежную страницу SimplePay для уточнения деталей
 */

$payment_data = new SimplePay_Payment;
$payment_data->amount = 100;
$payment_data->order_id = 1001;
$payment_data->client_name = 'Иван Иванов';
$payment_data->client_email = 'info@simplepay.pro';
$payment_data->client_phone = '79859202808';
$payment_data->description = 'Тест платежа';
$payment_data->payment_system = 'TEST';
$payment_data->client_ip = $_SERVER['REMOTE_ADDR'];

/* Пример использования */

// Создаем объект мерчант-класса SP
$sp = new MySimplePayMerchant();

$out = $sp->get_ps_list(100);
print_output("Разрешенные платежные системы", $out);

// Запрос на создание платежа
$out = $sp->direct_payment($payment_data);
print_output("Информация об адресе для переадресации пользователя", $out);

// Получаем ссылку на платежную страницу
$payment_link = $out['sp_redirect_url'];

// Выводим ссылку
echo '<a href="' . $payment_link . '" target="_blank">Переход на платежную страницу</a>';

// Запрос данных о созданном платеже
$out = $sp->get_order_status_by_order_id(1001);
print_output("Информация о созданном платеже", $out);

/**
 * Функция для выдачи данных результата на страницу
 * @param type $header
 * @param type $array
 */
function print_output($header, $array) {
    echo "<h1>$header</h1>";
    echo "<pre>";
    print_r($array);
    echo "</pre>";
}
