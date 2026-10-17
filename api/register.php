
<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function reply(int $code, array $data): void {
    http_response_code($code);
    echo json_encode(
        $data,
        JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
    );
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    reply(405, [
        'status' => false,
        'error' => 'POST method required.'
    ]);
}

$name = trim($_POST['name'] ?? '');
$num = trim($_POST['num'] ?? '');
$businessName = trim($_POST['business_name'] ?? '');

if ($name === '' || $num === '' || $businessName === '') {
    reply(400, [
        'status' => false,
        'error' => 'Name, phone number and business name are required.'
    ]);
}

if (
    mb_strlen($name, 'UTF-8') > 150 ||
    mb_strlen($num, 'UTF-8') > 30 ||
    mb_strlen($businessName, 'UTF-8') > 200
) {
    reply(400, [
        'status' => false,
        'error' => 'One or more fields are too long.'
    ]);
}

if (!preg_match('/^[0-9+\s().-]{5,30}$/', $num)) {
    reply(400, [
        'status' => false,
        'error' => 'Invalid phone number.'
    ]);
}

$payload = [
    'name' => $name,
    'phone_number' => $num,
    'business_name' => $businessName
];

$json = json_encode($payload, JSON_UNESCAPED_UNICODE);

if ($json === false) {
    reply(400, [
        'status' => false,
        'error' => 'Invalid request data.'
    ]);
}

$ch = curl_init(
    'https://api-merchant.carrybee.com/api/v2/merchant/register'
);

curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $json,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_SSL_VERIFYHOST => 2,
    CURLOPT_HTTPHEADER => [
        'Accept: application/json',
        'Content-Type: application/json'
    ]
]);

$response = curl_exec($ch);
$error = curl_error($ch);
$status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

curl_close($ch);

if ($response === false) {
    error_log('CarryBee request failed: ' . $error);
    reply(502, [
        'status' => false,
        'error' => 'Could not contact CarryBee.'
    ]);
}

$data = json_decode($response, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    error_log('CarryBee returned invalid JSON; HTTP ' . $status);
    reply(502, [
        'status' => false,
        'error' => 'Unexpected response from CarryBee.',
        'upstream_http_code' => $status
    ]);
}

http_response_code($status >= 100 && $status <= 599 ? $status : 502);
echo json_encode(
    $data,
    JSON_UNESCAPED_UNICODE |
    JSON_PRETTY_PRINT |
    JSON_UNESCAPED_SLASHES
);
