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
$isPostgres = db_driver() === 'pgsql';
$lockResult = $isPostgres
    ? $db->query("SELECT pg_try_advisory_lock(hashtext('kudla_automatic_sms'))")->fetchColumn()
    : $db->query("SELECT GET_LOCK('kudla_automatic_sms', 0)")->fetchColumn();
$lockAcquired = $isPostgres ? in_array($lockResult, [true, 1, '1', 't', 'true'], true) : (int)$lockResult === 1;
if (!$lockAcquired) {
    fwrite(STDOUT,"Another automatic SMS run is already active.\n");
    exit(0);
}

try {
    $recentCutoff = $isPostgres ? "s.created_at >= NOW() - INTERVAL '2 hours'" : 's.created_at >= DATE_SUB(NOW(), INTERVAL 2 HOUR)';
    $alertCooldown = $isPostgres ? "created_at >= NOW() - INTERVAL '6 hours'" : 'created_at >= DATE_SUB(NOW(), INTERVAL 6 HOUR)';
    $farmers = $db->query("SELECT u.email,u.phone,u.region,u.weather_location,u.crop_type,u.soil_quality,u.farmer_type,u.growth_stage,u.soil_type FROM users u WHERE u.sms_opt_in=TRUE AND u.phone IS NOT NULL AND u.region IS NOT NULL AND NOT EXISTS (SELECT 1 FROM sms_logs s WHERE s.farmer_email=u.email AND s.status='submitted' AND {$recentCutoff})")->fetchAll();
    foreach ($farmers as $farmer) {
        try {
            $weather = fetch_weather_location(($farmer['weather_location'] ?? '') ?: $farmer['region']);
            $alert = weather_risk_alert($weather);
            if ($alert === null) {
                continue;
            }
            $duplicate = $db->prepare("SELECT 1 FROM sms_logs WHERE farmer_email = ? AND status = 'submitted' AND message LIKE ? AND {$alertCooldown} LIMIT 1");
            $duplicate->execute([(string)$farmer['email'], 'agriSense weather alert ['.$alert['code'].']%']);
            if ($duplicate->fetchColumn()) {
                continue;
            }
            $location = (string)(($farmer['weather_location'] ?? '') ?: $farmer['region']);
            $text = 'agriSense weather alert ['.$alert['code'].'] for '.$location.': '.$alert['text'];
            $result = lunyazi_send_sms((string)$farmer['phone'], $text);
            record_lunyazi_attempt((string)$farmer['email'],(string)$farmer['phone'],$text,$result);
            if ($result['success']) {
                $messages = read_json(MESSAGES_FILE, []);
                $messages[] = ['id'=>count($messages)+1,'email'=>$farmer['email'],'message'=>$text,'sent_at'=>date('Y-m-d H:i:s'),'status'=>'submitted','provider'=>'Lunyazi','source'=>'automatic'];
                write_json(MESSAGES_FILE,$messages);
                echo "SMS submitted for an opted-in farmer.\n";
            } else {
                fwrite(STDERR,'SMS failed: '.$result['error']."\n");
            }
        } catch (Throwable $exception) {
            fwrite(STDERR,'SMS processing failed: '.$exception->getMessage()."\n");
        }
    }
    echo 'Processed '.count($farmers).' opted-in farmer(s).'.PHP_EOL;
} finally {
    if ($isPostgres) {
        $db->query("SELECT pg_advisory_unlock(hashtext('kudla_automatic_sms'))");
    } else {
        $db->query("SELECT RELEASE_LOCK('kudla_automatic_sms')");
    }
}
