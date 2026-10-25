<?php
require_once __DIR__.'/../includes/database.php';
require_once __DIR__.'/../modules/weather_service.php';
require_once __DIR__.'/../modules/farm_operations.php';
require_once __DIR__.'/../modules/sms_service.php';

if (LUNYAZI_SMS_API_KEY === '') {
    fwrite(STDERR,"Automatic SMS paused: configure LUNYAZI_SMS_API_KEY first.\n");
    exit(1);
}

$db = db();
$lock = (int)$db->query("SELECT GET_LOCK('kudla_automatic_sms', 0)")->fetchColumn();
if ($lock !== 1) {
    fwrite(STDOUT,"Another automatic SMS run is already active.\n");
    exit(0);
}

try {
    $farmers = $db->query("SELECT u.email,u.phone,u.region,u.weather_location,u.crop_type,u.soil_quality,u.farmer_type,u.growth_stage,u.soil_type FROM users u WHERE u.sms_opt_in=1 AND u.phone IS NOT NULL AND u.region IS NOT NULL AND NOT EXISTS (SELECT 1 FROM sms_logs s WHERE s.farmer_email=u.email AND s.status='submitted' AND s.created_at >= DATE_SUB(NOW(), INTERVAL 2 HOUR))")->fetchAll();
    foreach ($farmers as $farmer) {
        try {
            $weather = fetch_weather_location(($farmer['weather_location'] ?? '') ?: $farmer['region']);
            $tasks = farm_operations_advice($weather, $farmer);
            $text = 'agriSense farm tasks: '.farm_operations_sms_text($tasks);
            $result = lunyazi_send_sms((string)$farmer['phone'], $text);
            record_lunyazi_attempt((string)$farmer['email'],(string)$farmer['phone'],$text,$result);
            if ($result['success']) {
                $messages = read_json(MESSAGES_FILE, []);
                $messages[] = ['id'=>count($messages)+1,'email'=>$farmer['email'],'message'=>$text,'sent_at'=>date('Y-m-d H:i:s'),'status'=>'submitted','provider'=>'Lunyazi','source'=>'automatic'];
                write_json(MESSAGES_FILE,$messages);
                echo $farmer['email'].': SMS submitted.\n';
            } else {
                fwrite(STDERR,$farmer['email'].': '.$result['error']."\n");
            }
        } catch (Throwable $exception) {
            fwrite(STDERR,$farmer['email'].': '.$exception->getMessage()."\n");
        }
    }
    echo 'Processed '.count($farmers).' opted-in farmer(s).'.PHP_EOL;
} finally {
    $db->query("SELECT RELEASE_LOCK('kudla_automatic_sms')");
}
