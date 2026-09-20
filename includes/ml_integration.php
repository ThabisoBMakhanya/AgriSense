<?php
require_once __DIR__ . '/config.php';
function ml_call(string $action, array $input): ?array {
    $python=defined('PYTHON_BIN')?PYTHON_BIN:'python'; $payload=json_encode(['action'=>$action,'input'=>$input]);
    $command=escapeshellarg($python).' '.escapeshellarg(ML_DIR.'/run_ml.py').' '.escapeshellarg($payload); $output=@shell_exec($command.' 2>NUL'); $result=$output?json_decode($output,true):null; return is_array($result)?$result:null;
}
function classify_weather(array $weather): array { $result=ml_call('weather', $weather); if ($result && isset($result['classification'])) return $result; $score=($weather['rainfall']>3?1:0)+($weather['humidity']>45?1:0)+($weather['temperature']>=15&&$weather['temperature']<=32?1:0); $classes=['poor','moderate','good','excellent']; return ['classification'=>$classes[$score],'confidence'=>78]; }
function recommend_crops(array $weather,string $region): array { $result=ml_call('crops',array_merge($weather,['region'=>$region])); return $result['recommendations']??[['crop'=>'Maize','confidence'=>86,'reason'=>'Reliable staple crop for current conditions.'],['crop'=>'Beans','confidence'=>74,'reason'=>'Good fit for moderate temperatures and moisture.'],['crop'=>'Cabbage','confidence'=>63,'reason'=>'Suitable where irrigation is available.']]; }
function predict_yield(array $weather,array $user): array { $result=ml_call('yield',array_merge($weather,['soil_quality'=>soil_value($user['soil_quality']??2),'farm_size'=>(float)($user['farm_size']??1)])); if ($result) return $result; $per=1200+soil_value($user['soil_quality']??2)*1150; return ['yield_kg_ha'=>$per,'total_yield_kg'=>round($per*(float)($user['farm_size']??1),1),'status'=>$per>=5500?'Excellent':($per>=4000?'Good':($per>=2500?'Average':'Below Average'))]; }
