<?php

/**
 * Клиентский класс для работы с SimplePay API
 * Является абстрактным. Унаследованный класс должен иметь заполненные свойства,
 * а также имплементировать методы process_success() и process_fail() для обработки 
 * соответствующих статусов.
 */
abstract class SimplePayAbstractMerchant {

    protected
            $outlet_id,
            $secret_key,
            $secret_key_result,
            $hash_algo = "MD5",
            $result_url;
    /*
     * Этот параметр отвечает за опцию SSL Verifypeer. Отключать рекомендуется 
     * только в случае, если у Вас по какой-то причине на сервере нет корневых 
     * сертификатов.
     */
    protected 
            $strong_ssl = true;
    static
            /**
             * URL для совершения платежа в обычном режиме
             */
            $_SP_Payment_URL_Secure = "https://api.simplepay.pro/sp/payment",
            /**
             * URL для совершения платежа в режиме прямого взаимодействия
             */
            $_SP_Payment_URL_Direct_Secure = "https://api.simplepay.pro/sp/init_payment",
            /**
             * URL для совершения рекуррентного платежа
             */
            $_SP_Recurring_Payment_URL_Secure = "https://api.simplepay.pro/sp/make_recurring_payment",
            /**
             * URL для проверки статуса платежа
             */
            $_SP_Status_URL_Secure = "https://api.simplepay.pro/sp/get_status",
            /**
             * URL для проведения возврата платежа
             */
            $_SP_Refund_URL_Secure = "https://api.simplepay.pro/sp/refund",
            /**
             * URL для получения списка допустимых платежных систем
             */
            $_SP_PS_List_URL_Secure = "https://api.simplepay.pro/sp/ps_list";

    /**
     * Абстрактный метод, получает ID заказа в ситеме мерчанта и массив входящих параметров
     * в случае получения уведомления с успешным статусом
     * @param int $order_id Номер заказа
     * @param array $request_params Входящие параметры запроса SimplePay
     */
    abstract function process_success($order_id, $request_params);

    /**
     * Абстрактный метод, получает ID заказа в ситеме мерчанта и массив входящих параметров
     * в случае получения уведомления со статусом отмена/отказ
     * @param int $order_id Номер заказа
     * @param array $request_params Входящие параметры запроса SimplePay
     */
    abstract function process_fail($order_id, $request_params);

    /**
     * Создает счет и переадресует пользователя на платежную страницу SimplePay, 
     * либо платежную страницу платежной системы.
     * @param SimplePay_Payment $payment Запрос на создание платежа
     */
    function payment(SimplePay_Payment $payment) {

        $arrReq = $this->generate_payment_params($payment);

        // Параметры безопасности сообщения. Необходима генерация sp_salt и подписи сообщения.
        $arrReq['sp_salt'] = rand(21, 43433);

        // подписываем запрос
        $arrReq['sp_sig'] = $this->make_signature_string_request($arrReq, 'payment');

        $query = http_build_query($arrReq);

        // перенаправляем пользователя
        header("Location: " . self::$_SP_Payment_URL_Secure . "?$query");
        die("Идет переадресация на страницу платежной системы...");
    }

    /**
     * Создает счет и возвращает параметры для переадресации на платежную страницу SimplePay, 
     * либо платежную страницу платежной системы.
     * @param SimplePay_Payment $payment Запрос на создание платежа
     * @return array
     */
    function direct_payment(SimplePay_Payment $payment){
        $arrReq = $this->generate_payment_params($payment);

        return $this->make_sp_json_request(
                $arrReq, 
                self::$_SP_Payment_URL_Direct_Secure
        );
    }
    
    /**
     * Получение списка допустимых платежных систем для данной торговой точки 
     * и суммы платежа
     * @param decimal $amount Сумма платежа
     * @return array
     */
    function get_ps_list($amount){
        $arrReq = array(
            'sp_outlet_id' => $this->outlet_id,
            'sp_amount' => $amount
        );

        return $this->make_sp_json_request(
                $arrReq, 
                self::$_SP_PS_List_URL_Secure
        );
    }
    
    /**
     * Получение статуса заказа по номеру заказа в системе магазина
     * @param int $order_id ID заказа в системе магазина
     * @return array
     */
    function get_order_status_by_order_id($order_id){    
        
        $arrReq = array(
            'sp_outlet_id' => $this->outlet_id,
            'sp_order_id' => abs(intval($order_id))
        );

        return $this->make_sp_json_request(
                $arrReq, 
                self::$_SP_Status_URL_Secure
        );
    }
    
    /**
     * Получение статуса заказа по номеру заказа в системе SimplePay
     * @param int $transaction_id ID транзакции в SimplePay
     * @return array
     */
    function get_order_status_by_transaction_id($transaction_id){
        
        $arrReq = array(
            'sp_outlet_id' => $this->outlet_id,
            'sp_payment_id' => abs(intval($transaction_id))
        );
        
        return $this->make_sp_json_request(
                $arrReq, 
                self::$_SP_Status_URL_Secure
        );
    }
    
    /**
     * Создание возврата по ID транзакции
     * @param int $transaction_id ID транзакции в SimplePay
     * @param decimal $amount Сумма (по умолчанию - будет создан возврат на полную сумму
     * @param string $description Назначение возврата (по умолчанию - "Возврат")
     * @return array
     */
    function make_refund_by_transaction_id($transaction_id, $amount = 0, $description = 'Возврат'){
        
        $arrReq = array(
            'sp_outlet_id' => $this->outlet_id,
            'sp_refund_amount' => floatval($amount),
            'sp_payment_id' => abs(intval($transaction_id)),
            'sp_description' => $description
        );

        return $this->make_sp_json_request(
                $arrReq, 
                self::$_SP_Refund_URL_Secure
        );
    }
    
    /**
     * Создание возврата по ID заказа в системе магазина
     * @param int $order_id ID заказа в системе магазина
     * @param decimal $amount Сумма (по умолчанию - будет создан возврат на полную сумму
     * @param string $description Назначение возврата (по умолчанию - "Возврат")
     * @return array
     */
    function make_refund_by_order_id($order_id, $amount = 0, $description = 'Возврат'){
        
        $arrReq = array(
            'sp_outlet_id' => $this->outlet_id,
            'sp_refund_amount' => floatval($amount),
            'sp_order_id' => abs(intval($order_id)),
            'sp_description' => $description
        );

        return $this->make_sp_json_request(
                $arrReq, 
                self::$_SP_Refund_URL_Secure
        );
    }

    /**
     * Создание рекуррентного платежа
     * @param int $recurring_profile_id ID рекуррентного профиля
     * @param int $order_id ID заказа в системе продавца
     * @param string $description Назначение платежа
     * @param decimal $amount Сумма (по умолчанию будет равна сумме исходного платежа)
     * @return array
     */
    function make_recurring_payment($recurring_profile_id, $order_id, $description, $amount = NULL){
        $arrReq = array(
            'sp_outlet_id' => $this->outlet_id,
            'sp_recurring_profile' => $recurring_profile_id,
            'sp_order_id' => abs(intval($order_id)),
            'sp_description' => $description,
            'sp_amount' => $amount
        );

        return $this->make_sp_json_request(
                $arrReq, 
                self::$_SP_Recurring_Payment_URL_Secure
        );
    }
    
    /**
     * Обрабатывает входящие данные из глобальных $_POST и $_GET, 
     * определяет формат взаимоджействия и разбирает результат платежа
     */
    function process_result_request() {

        // Декодируем входящие параметры запроса
        $REQUEST_PARAMS = $this->decode_input_request_data();

        // если мы получили данные из запроса
        if (empty($REQUEST_PARAMS['sp_sig'])) {
            die("Некорректные входящие параметры");
        }
        
        // Убираем все параметры, не имеющие отношения к SimplePay
        $request_keys = array_keys($REQUEST_PARAMS);
        foreach($request_keys as $field){
            if(!strstr($field,'sp_')){
                unset($REQUEST_PARAMS[$field]);
            }
        }
        
        // получаем имя скрипта result
        $result_script_name = basename($this->result_url);
        
        // формируем правильную подпись для полученного набора параметров
        $right_sig = $this->make_signature_string_result($REQUEST_PARAMS, $result_script_name);
        
        // проверяем входящую подпись
        if ($REQUEST_PARAMS['sp_sig'] != $right_sig) {
            die("Некорректная подпись запроса");
        }

        // если подпись верна, продолжаем
        $order_id = $REQUEST_PARAMS['sp_order_id'];

        // проверяем статус платежа в уведомлении
        if ($REQUEST_PARAMS['sp_result'] == 1) {
            // обрабатываем случай успешной оплаты заказа с номером $order_id
            $this->process_success($order_id, $REQUEST_PARAMS);
            $answer_description = "Оплата принята";
        } else {
            // заказ с номером $order_id не будет оплачен.
            $this->process_fail($order_id, $REQUEST_PARAMS);
            $answer_description = "Оплата отменен";
        }

        // сформимируем ответ в общем виде
        $answer = array(
            'sp_status' => 'ok',
            'sp_description' => $answer_description,
            'sp_salt' => $REQUEST_PARAMS['sp_salt']
        );

        // формируем и добавляем подпись
        $answer['sp_sig'] = $this->make_signature_string_result($answer, $result_script_name);

        // если мы приняли данные через JSON - отвечаем также JSON
        if (!empty($_POST['sp_json'])) {
            $json = json_encode(array('response' => $answer), JSON_UNESCAPED_UNICODE);
            header('Content-type: application/json');
            die($json);
        }
        // во всех остальных случаях нужно ответить SimplePay XML-ответом
        else {
            $xml = $this->array2xml_with_header($answer, 'response');
            header('Content-type: text/xml');
            die($xml);
        }
        
    }

    //
    // <// <editor-fold defaultstate="collapsed" desc="Служебные методы">
    //
    
    /**
     * Создает массив с параметрами запроса API SimplePay из запроса на платеж
     * @param SimplePay_Payment $payment Запрос на платеж
     * @return array Массив с параметрами запроса к SimplePay
     */
    private function generate_payment_params(SimplePay_Payment $payment) {

        $arrReq = array();

        /* Обязательные параметры */
        
        // Идентификатор торговой точки
        $arrReq['sp_outlet_id'] = $this->outlet_id;
        // Идентификатор заказа в системе магазина
        $arrReq['sp_order_id'] = $payment->order_id;
        // Сумма заказа
        $arrReq['sp_amount'] = $payment->amount;    
        // Время жизни счёта (в секундах)
        $arrReq['sp_lifetime'] = $payment->lifetime;  
        // Описание заказа (показывается в Платёжной системе)
        $arrReq['sp_description'] = $payment->description; 
        
        /* Необязательные параметры */
        
        // Имя плательщика
        $arrReq['sp_user_name'] = $payment->client_name;    
        // e-mail плательщика
        $arrReq['sp_user_contact_email'] = $payment->client_email; 
        // мобильный плательщика
        $arrReq['sp_user_phone'] = $payment->client_phone; 
        // IP-адрес плательщика
        $arrReq['sp_user_ip'] = $payment->client_ip;  
        // дополнительный параметр
        $arrReq['sp_user_params'] = $payment->user_params;          
        
        // если переопределен Result URL
        if(!empty($payment->result_url)){
            $arrReq['sp_result_url'] = $payment->result_url;
        } else{
            $arrReq['sp_result_url'] = $this->result_url;
        }
        
        // если переопределен Success URL
        $arrReq['sp_success_url'] = $payment->success_url;
        
        // если переопределен Fail URL
        $arrReq['sp_failure_url'] = $payment->fail_url;
        
        // Название ПС из справочника ПС (см документацию). Задаётся, если не требуется выбор ПС. 
        // Если не задано, выбор будет предложен пользователю на сайте SimplePay
        $arrReq['sp_payment_system'] = $payment->payment_system;

        // если надо инициализировать рекурентный профиль
        if ($payment->recurrent_start) {
            $arrReq['sp_recurring_start'] = 1;
        }
        
        // убираем пустые элементы и возвращаем результат
        return array_filter($arrReq);
    }

    /**
     * Разбирает данные в глобальных массивах на предмет наличия запроса от SimplePay.
     * Автоматически определяет тип запроса: GET/POST/XML/JSON. Возвращает массив с параметрами или false
     * @return array
     */
    private function decode_input_request_data() {
        
        // XML
        if (!empty($_POST['sp_xml'])) {
            $array = $this->unpack_xml($_POST['sp_xml']);
            return $array;
        }
        // JSON
        else if (!empty($_POST['sp_json'])) {
            $array = json_decode($_POST['sp_json'], true);
            return $array[0];
        }
        // GET
        else if (!empty($_GET['sp_sig'])) {
            return $_GET;
        }
        // POST
        else if (!empty($_POST['sp_sig'])) {
            return $_POST;
        }
        // Ошибка
        else {
            return false;
        }
    }
    
    /**
     * Создание и отправка подписанного JSON-запроса к API SimplePay и разбор
     * полученного JSON-ответа в массив
     * @param type $data_array
     * @param type $request_url
     * @return boolean
     */
    private function make_sp_json_request($data_array, $request_url){
        
        $arrReq = $data_array;
        
        // Параметры безопасности сообщения. Необходима генерация sp_salt и подписи сообщения.
        $arrReq['sp_salt'] = rand(21, 43433);

        // подписываем запрос
        $script_name = basename($request_url);
        $arrReq['sp_sig'] = $this->make_signature_string_request($arrReq, $script_name);
        
        // Подготовим и отправим JSON-запрос
        $answer = $this->curl_post(
                $request_url, 
                array('sp_json' => json_encode($arrReq)),
                $this->strong_ssl
                );
        
        // Раскодируем полученный JSON-ответ
        $decoded_answer = json_decode($answer,true);
        
        if(is_array($decoded_answer)){
            return $decoded_answer;
        } else{
            return false;
        }
    }
    
    //
    // <// <editor-fold defaultstate="collapsed" desc="Низкий уровень">
    //
    
    /**
     * Статический метод для рекурсивной сортировки массива по именам ключей
     * @param array $array входящий массив для сортировки
     * @param int $sort_flags Флаги для сортировки, по-умолчанию - SORT_REGULAR
     * @return boolean
     */
    public static function ksort_recursive(&$array, $sort_flags = SORT_REGULAR) {
        // если это не массив - сразу вернем false
        if (!is_array($array)) {
            return false;
        }

        ksort($array, $sort_flags);

        foreach ($array as &$arr) {
            self::ksort_recursive($arr, $sort_flags);
        }

        return true;
    }

    /**
     * Формирование подписи по алгоритму SimplePay.
     * @param array $array Ассоациативный массив с параметрами для подписи
     * @param string $script_name Имя скрипта, к которому будет адресовано подписываемое сообщение
     * @param string $secret_key Секретный ключ торговой точки
     * @param string $hash_algo Алгоритм хеширования: MD5 (по-умолчанию), SHA256, SHA512.
     * @return string Полученная подпись
     */
    public static function make_signature_string($array, $script_name, $secret_key, $hash_algo = 'MD5') {

        // ансетим подпись, если она уже присутствовала в массиве запроса
        unset($array['sp_sig']);
        
        // 1. отсортируем массив по ключам, рекурсивно
        self::ksort_recursive($array);

        $values_str = implode(';', array_values($array));
        
        $concat_string = $script_name . ';' . $values_str . ';' . $secret_key;
        
        if (strtolower($hash_algo) == 'md5') {
            return md5($concat_string);
        } else {
            return hash($hash_algo, $concat_string);
        }
    }

    /**
     * Генератор подписи для запроса платежа
     * @param array $array Ассоациативный массив с параметрами для подписи
     * @param string $script_name Имя скрипта, к которому будет адресовано подписываемое сообщение
     * @return string
     */
    private function make_signature_string_request($array, $script_name) {
        return self::make_signature_string(
                $array, 
                $script_name, 
                $this->secret_key, 
                $this->hash_algo);
    }

    /**
     * Генератор подписи для оповещения Result
     * @param array $array Ассоациативный массив с параметрами для подписи
     * @param type $script_name Имя скрипта, к которому будет адресовано подписываемое сообщение (Result URL торговой точки)
     * @return string
     */
    private function make_signature_string_result($array, $script_name) {
        return self::make_signature_string(
                $array, 
                $script_name, 
                $this->secret_key_result, 
                $this->hash_algo);
    }

    /**
     * Отправка данных POST-запросом через CURL, возвращает тело ответа
     * @param string $url URL запрашиваемого ресурса
     * @param array $params Массив с параметрами
     * @param boolean $verifypeer Опция CURL CURLOPT_SSL_VERIFYPEER (по умолчанию false)
     * @return string
     */
    private function curl_post($url, $params, $verifypeer = false) {
        if ($curl = curl_init()) {
            $query = http_build_query($params);
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, $verifypeer);
            curl_setopt($curl, CURLOPT_URL, $url);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $query);
            $out = curl_exec($curl);
            curl_close($curl);
            return $out;
        } else {
            return false;
        }
    }

    /**
     * Запрос ресурса методом GET через CURL,  возвращает тело ответа
     * @param string $url URL запрашиваемого ресурса
     * @param boolean $verifypeer Опция CURL CURLOPT_SSL_VERIFYPEER (по умолчанию false)
     * @return string
     */
    private function curl_get($url, $verifypeer = false) {
        if ($curl = curl_init()) {
            curl_setopt($curl, CURLOPT_URL, $url);
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, $verifypeer);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            $out = curl_exec($curl);
            curl_close($curl);
            return $out;
        } else {
            return false;
        }
    }

    /**
     * Распаколвка XML в ассоациативный массив
     * @param string $request XML-запрос
     * @return array
     */
    private function unpack_xml($request) {
        $dom = new DOMDocument;
        $dom->loadXML($request);
        $parsed_xml = $s = simplexml_import_dom($dom);

        $as_array = (array) $parsed_xml;
        return $as_array;
    }

    /**
     * Создает XML с XML-заголовком "<?xml>" на основе ассоциативного массива 
     * @param array $array Входящие данные
     * @param string $root_node Корневой тег, в который будет вложенны данные
     * @return type
     */
    private function array2xml_with_header($array, $root_node = false) {
        $total_xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $total_xml .= $this->array2xml($array, $root_node);
        return $total_xml;
    }

    /**
     * Создает XML на основе ассоциативного массива
     * @param array $array Входящие данные
     * @param string $root_node Корневой тег, в который будет вложенны данные
     * @return string
     */
    private function array2xml($array, $root_node = false) {

        $total_xml = '';
        if ($root_node) {
            $total_xml .= "<" . $root_node . ">\n";
        }

        foreach ($array as $item => $val) {
            if (is_array($val)) {
                $total_xml .= array2xml($val, $item);
            } else {
                $total_xml .= "\t" . '<' . $item . '>' . $val . '</' . $item . '>' . "\n";
            }
        }

        if ($root_node) {
            $total_xml .= "</" . $root_node . ">\n";
        }

        return $total_xml;
    }
    // </editor-fold>
    // </editor-fold>
}

// <// <editor-fold defaultstate="collapsed" desc="SimplePay_Payment - структура данных для инициализации платежа">
/**
 * Структура с данными иницализации платежа
 */
class SimplePay_Payment {

    /**
     *
     * @var decimal Сумма платежа
     */
    public $amount;

    /**
     *
     * @var string Имя плательщика
     */
    public $client_name;

    /**
     *
     * @var string e-mail плательщика
     */
    public $client_email;

    /**
     *
     * @var string телефон плательщика
     */
    public $client_phone;

    /**
     *
     * @var string IP плательщика (нужно передавать для прямой переадресации на страницу ПС)
     */
    public $client_ip;

    /**
     *
     * @var string Назначение платежа
     */
    public $description;

    /**
     *
     * @var int Номер заказа в системе продавца 
     */
    public $order_id;

    /**
     *
     * @var boolean  Если нужно инициализировать рекуррентный профиль - поставить true
     */
    public $recurrent_start = false;

    /**
     *
     * @var int Срок действия транзакции - сутки
     */
    public $lifetime = 86400;

    /**
     *
     * @var string Дополнительный параметр
     */
    public $user_params = NULL;

    /**
     *
     * @var string Идентификатор платежной системы в SimplePay 
     */
    public $payment_system = NULL;

    /**
     *
     * @var string Переопределить установленный в настройках точки Result URL 
     */
    public $result_url = NULL;
    
    /**
     *
     * @var string Переопределить установленный в настройках точки Success URL 
     */
    public $success_url = NULL;
    
    /**
     *
     * @var string Переопределить установленный в настройках точки Fail URL 
     */
    public $fail_url = NULL;
}

// </editor-fold>
