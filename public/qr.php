<?php
$your_data = '00020101021153037645802TH29370016A000000677010111011300669241965705406890.0063044F95';
echo '<img src="https://api.qrserver.com/v1/create-qr-code/?data=' . urlencode($your_data) . '&size=200x200">';
