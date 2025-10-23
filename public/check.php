<?php

function extractRawNumbersUnicodeSafe(string $text): array
{
    // 1. Normalize Unicode dash เช่น ๐๘๐–๘–๐๔๘๐๙–๔ ให้กลายเป็น ASCII
    $text = str_replace(['–', '−', '‒', '—', '―'], '-', $text); // แดชทุกชนิด
    $text = preg_replace('/[^\x20-\x7E]/u', '', $text); // ตัด Unicode แปลก ๆ ออก

    // 2. ลบ @username ออก
    $text = preg_replace('/@\w+/', '', $text);

    // 3. เอาเฉพาะตัวเลข (รวมเลขที่คั่นด้วย -, ช่องว่าง)
    preg_match_all('/(?:\d[\s\-]?){9,}/', $text, $matches);

    $raws = [];

    foreach ($matches[0] as $m) {
        $onlyDigits = preg_replace('/\D/', '', $m); // เหลือแต่ตัวเลข
        if (strlen($onlyDigits) >= 9) {
            $raws[] = $onlyDigits;
        }
    }

    return array_values(array_unique($raws));
}
//$start = now()->subWeek()->startOfWeek();
//$end = now()->subDay()->endOfDay();

$content = "วรวุธ แก้วกอง

เลขที่บัญชี 080-8-04809-4
ธนาคาร กสิกรไทย

เบอร์ 09-1063-0962
User Telegram @Zaamon
User Id: 8145699466";
$numbers = extractRawNumbersUnicodeSafe($content);
print_r($numbers);