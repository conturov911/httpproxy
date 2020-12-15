<?php
//ini_set('error_reporting', E_ALL);
//ini_set('display_errors', 1);
//ini_set('display_startup_errors', 1);

$domain='https://domain.tld';

$method = $_SERVER['REQUEST_METHOD'];
$dataType = filter_input(INPUT_GET, 'dataType', FILTER_DEFAULT, ['options' => ['default' => 'text/html; charset=utf-8']]);

$headers = [];

foreach ($_SERVER as $name => $value) {
    if (in_array($name, ['HTTP_HOST', 'HTTP_ACCEPT_ENCODING']) || ('HTTP_' !== substr($name, 0, 5) && !in_array($name, ['CONTENT_TYPE', 'CONTENT_LENGTH']))) {
        continue;
    }

    $name = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))));
    if($name == 'X-Original-Url'){
        continue;
    }
    $headers[] = $name . ':' . $value;
}

$url = $domain . $_SERVER['REQUEST_URI'] ?? '';

$ch = curl_init($url);
$curlOpts = [
    CURLOPT_URL            => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => $headers,
    CURLOPT_SSL_VERIFYHOST => 0,
    CURLOPT_SSL_VERIFYPEER => 0,
    CURLOPT_MAXREDIRS => 3,
    CURLOPT_TIMEOUT => 0,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1
];

if(!empty($headers['Cookie'])){
    $curlOpts[CURLOPT_COOKIE] = $headers['Cookie'];
}

$headersHandler = new CurlResponseHeadersHandler($ch);

if ('GET' !== $method) {
    $curlOpts[CURLOPT_CUSTOMREQUEST] = $method;
}

if (in_array($method, ['PUT', 'PATCH']) || 'POST' === $method && empty($_FILES)) {
    $curlOpts[CURLOPT_POSTFIELDS] = file_get_contents('php://input');
}elseif($method == "POST") {
    $data_str = array();
    if(!empty($_FILES)) {
        foreach ($_FILES as $key => $value) {
            $full_path = realpath( $_FILES[$key]['tmp_name']);
            $data_str[$key] = '@'.$full_path;
        }
    }

    curl_setopt($ch, CURLOPT_POSTFIELDS, $data_str+$_POST);
}

curl_setopt_array($ch, $curlOpts);

$result = curl_exec($ch);


$contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$requestTime = curl_getinfo($ch, CURLINFO_TOTAL_TIME);
$targetIP = curl_getinfo($ch, CURLINFO_PRIMARY_IP);
curl_close($ch);

$cookies = $headersHandler->getCookies();
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS, PUT');
header('Content-Type: '.$contentType);
header('X-RequestTime: '.$requestTime);
header('X-RequestTarget: '.$targetIP);
header('X-XSS-Protection: 0');
header('X-Frame-Options: ALLOWALL');
header('X-Content-Type-Options: nosniff');
header('Strict-Transport-Security: max-age=0');
header('Set-Cookie: ' . $cookies[0]);



http_response_code($httpCode);

echo $result;

class CurlResponseHeadersHandler {
    private $cookies;

    public function __construct($curl) {
        $this->cookies = array();
        curl_setopt($curl, CURLOPT_HEADERFUNCTION, array($this, 'responseHeaderCallback') );
    }

    function responseHeaderCallback($curl, $headerLine) {
        if ( strpos($headerLine, 'set-cookie:') !== false )
            $cookie = str_replace('set-cookie: ', '', $headerLine);
        if ( strpos($headerLine, 'Set-Cookie:') !== false )
            $cookie = str_replace('Set-Cookie: ', '', $headerLine);

        if ( isset($cookie) ) {
            array_push($this->cookies, $cookie);
        }

        return strlen($headerLine);
    }

    public function getCookies() {
        return $this->cookies;
    }
}

