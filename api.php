<?php

header("Access-Control-Allow-Origin: *");

error_reporting(E_ALL);

$mst = $_GET['mst'] ?? '';

if (empty($mst)) {
    echo json_encode([
        "success" => false,
        "message" => "Thiếu mã số thuế"
    ]);
    exit;
}

// TOKEN CỦA BẠN
$token = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJodHRwOi8vc2NoZW1hcy54bWxzb2FwLm9yZy93cy8yMDA1LzA1L2lkZW50aXR5L2NsYWltcy9uYW1laWRlbnRpZmllciI6Ijk3MzIiLCJodHRwOi8vc2NoZW1hcy54bWxzb2FwLm9yZy93cy8yMDA1LzA1L2lkZW50aXR5L2NsYWltcy9uYW1lIjoiMDQwMTQ4NjkwMTk5OUBLVFQiLCJodHRwOi8vc2NoZW1hcy54bWxzb2FwLm9yZy93cy8yMDA1LzA1L2lkZW50aXR5L2NsYWltcy9lbWFpbGFkZHJlc3MiOiJtaW5odGh1LjAyOTVAZ21haWwuY29tIiwiQXNwTmV0LklkZW50aXR5LlNlY3VyaXR5U3RhbXAiOiJGSkFFTFhOT0UzT1o2RU4zWFFJWU1WQ0oyWURaQzJPUCIsImh0dHA6Ly9zY2hlbWFzLm1pY3Jvc29mdC5jb20vd3MvMjAwOC8wNi9pZGVudGl0eS9jbGFpbXMvcm9sZSI6WyJLdHQiLCJUZW12ZUtUVCIsIlRlc3RwcSIsIkJJRU5MQUkiXSwiaHR0cDovL3d3dy5hc3BuZXRib2lsZXJwbGF0ZS5jb20vaWRlbnRpdHkvY2xhaW1zL3RlbmFudElkIjoiMiIsIm1zdCI6IjA0MDE0ODY5MDEtOTk5IiwidGVuYW50bmFtZSI6IkhPQURPTjAxIiwic3ViIjoiOTczMiIsImp0aSI6IjM3NGQxMjJhLTJhN2QtNDZjOC1iMTU2LWE3MzYzNDc0ZDhjZSIsImlhdCI6MTc4MTI2NDcyMiwidG9rZW5fdmFsaWRpdHlfa2V5IjoiYTVlNDYzOGEtMTlmYy00NGZkLTlkZjQtOTJmMDhlNjE0ZjVhIiwidXNlcl9pZGVudGlmaWVyIjoiOTczMkAyIiwibmJmIjoxNzgxMjY0NzIyLCJleHAiOjE3ODM4NTY3MjIsImlzcyI6IkFicE5ldDgiLCJhdWQiOiJBYnBOZXQ4In0.e5UAchxXgiJvI88JIfg2Wh-j58vP2-Bu1gylZ7KElAg';

$url = "https://hddt.vin-hoadon.com/api/services/hddt/HoaDon/TraCuuMST?mst=" . urlencode($mst);

$ch = curl_init();

curl_setopt_array($ch, [
    CURLOPT_URL => $url,
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        "Authorization: Bearer " . $token
    ],

    // Tạm tắt SSL để test local
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false
]);

$result = curl_exec($ch);

if ($result === false) {

    echo json_encode([
        "success" => false,
        "error" => curl_error($ch)
    ]);

    exit;
}

header('Content-Type: application/json; charset=utf-8');

echo $result;