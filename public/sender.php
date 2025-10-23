<?php

$botToken = '7402693899:AAFIaaSeReP1xgshjid5KIDRKvssXOluP9A';    // แทนที่ด้วย token ของบอทคุณ
$chatId   = '-1002640883898';      // แทนที่ด้วย chat id ที่ต้องการส่งข้อความ
$message  = $_GET['message'];

// URL สำหรับเรียกใช้งาน sendMessage API
$url = "https://api.telegram.org/bot{$botToken}/sendMessage";

// เตรียมข้อมูลที่จะส่ง
$data = [
    'chat_id' => $chatId,
    'text'    => $message
];

// ส่ง POST request ด้วย cURL
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$result = curl_exec($ch);
curl_close($ch);

// แสดงผลลัพธ์จาก Telegram
echo $result;

