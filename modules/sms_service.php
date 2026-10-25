<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';

function lunyazi_send_sms(string $phone, string $message): array
{
    $apiKey = LUNYAZI_SMS_API_KEY;
    if ($apiKey === '') return ['success'=>false,'error'=>'SMS is not connected. Add a Lunyazi API key to includes/secrets.local.php.'];
    $phone = preg_replace('/[\s().-]/', '', $phone);
    if (!preg_match('/^\+2687\d{7}$/', $phone)) return ['success'=>false,'error'=>'The saved phone number is not a valid Eswatini mobile number. Update it in your profile.'];
    if ($message === '' || strlen($message) > 1200) return ['success'=>false,'error'=>'The advice text is empty or too long to send.'];

    $body = json_encode(['to'=>$phone,'message'=>$message], JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
    $headers = "X-Api-Key: {$apiKey}\r\nContent-Type: application/json\r\nAccept: application/json\r\n";
    $status = 0;
    $caBundle = getenv('WEATHER_CA_BUNDLE') ?: 'C:/xampp/apache/bin/curl-ca-bundle.crt';
    if (!is_file($caBundle)) $caBundle = (string)(ini_get('curl.cainfo') ?: ini_get('openssl.cafile'));

    if (function_exists('curl_init')) {
        $ch = curl_init(LUNYAZI_SMS_API_URL);
        $options = [CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$body,CURLOPT_HTTPHEADER=>['X-Api-Key: '.$apiKey,'Content-Type: application/json','Accept: application/json'],CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>8,CURLOPT_TIMEOUT=>20];
        if ($caBundle !== '' && is_file($caBundle)) $options[CURLOPT_CAINFO] = $caBundle;
        curl_setopt_array($ch,$options);
        $response = curl_exec($ch);
        $status = (int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);
        $curlError = curl_errno($ch);
        curl_close($ch);
        if ($response === false) return ['success'=>false,'error'=>"Could not connect to the SMS provider (cURL error {$curlError})."];
    } else {
        $ssl = ['verify_peer'=>true,'verify_peer_name'=>true];
        if ($caBundle !== '' && is_file($caBundle)) $ssl['cafile'] = $caBundle;
        $context = stream_context_create(['http'=>['method'=>'POST','header'=>$headers,'content'=>$body,'timeout'=>20,'ignore_errors'=>true],'ssl'=>$ssl]);
        $response = @file_get_contents(LUNYAZI_SMS_API_URL,false,$context);
        foreach (($http_response_header ?? []) as $header) {
            if (preg_match('/^HTTP\/\S+\s+(\d{3})/',$header,$match)) { $status=(int)$match[1]; break; }
        }
        if ($response === false) return ['success'=>false,'error'=>'Could not connect to the SMS provider over HTTPS.'];
    }

    $result = json_decode($response,true);
    if (!is_array($result)) return ['success'=>false,'error'=>'The SMS provider returned an unreadable response.'];
    if ($status >= 200 && $status < 300 && ($result['success'] ?? false) === true) {
        return ['success'=>true,'provider'=>'Lunyazi','cost'=>$result['cost']??null,'segments'=>$result['segments']??null,'credits_remaining'=>$result['credits_remaining']??null];
    }
    $providerError = (string)($result['error'] ?? $result['message'] ?? 'Request rejected');
    return ['success'=>false,'error'=>"SMS provider rejected the request (HTTP {$status}): {$providerError}"];
}

function record_lunyazi_attempt(string $email, string $phone, string $message, array $result): void
{
    $status = !empty($result['success']) ? 'submitted' : 'failed';
    $provider = $result['provider'] ?? 'Lunyazi';
    $serialized = json_encode($result, JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE);
    try {
        $statement = db()->prepare('INSERT INTO sms_logs (farmer_email,recipient,message,status,provider,provider_response_json,created_at) VALUES (?,?,?,?,?,?,NOW())');
        $statement->execute([$email,$phone,$message,$status,$provider,$serialized]);
    } catch (Throwable $exception) {
        error_log('Could not record SMS delivery attempt: '.$exception->getMessage());
    }
    $detail = !empty($result['success']) ? 'provider='.$provider.' segments='.($result['segments']??'unknown') : ($result['error']??'unknown error');
    file_put_contents(LOG_DIR.'/sms_log.txt',date('c').' | '.$phone.' | '.$status.' | '.str_replace(["\r","\n"],' ',$detail).PHP_EOL,FILE_APPEND|LOCK_EX);
}
