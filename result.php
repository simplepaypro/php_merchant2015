<?php
/*
 * Здесь ничего менять не нужно
 */
require 'MySimplePayMerchant.class.php';

$sp = new MySimplePayMerchant();
$sp->process_result_request();
