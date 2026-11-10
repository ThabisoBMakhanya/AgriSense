<?php
require_once __DIR__.'/../includes/database.php';
require_once __DIR__.'/../ml/predict.php';
function advisory_features(array $weather,array $farmer): array
{
    $month=(int)date('n');$season=($month>=11||$month<=3)?'rainy':(($month>=5&&$month<=8)?'dry':'harmattan');
    $condition=strtolower((string)($weather['condition']??''));$condition=str_contains($condition,'storm')||str_contains($condition,'thunder')?'storm':(str_contains($condition,'rain')||str_contains($condition,'drizzle')?'rain':(str_contains($condition,'cloud')?'cloudy':'clear'));
    $type=strtolower((string)($farmer['farmer_type']??'crop'));if(!in_array($type,['crop','livestock','poultry','fisheries','beekeeping'],true))$type='crop';
    $soil=strtolower((string)($farmer['soil_type']??''));if(!in_array($soil,['sandy','loamy','clay'],true)){$quality=(int)($farmer['soil_quality']??2);$soil=$quality<=1?'sandy':($quality>=4?'clay':'loamy');}
    return ['temp_c'=>(float)($weather['temperature']??0),'humidity_pct'=>(int)($weather['humidity']??0),'rain_probability'=>(float)($weather['rain_probability']??0),'wind_kmh'=>(float)($weather['wind_kmh']??((float)($weather['wind_speed']??0)*3.6)),'cloud_pct'=>(int)($weather['cloud_pct']??0),'condition_encoded'=>$condition,'farmer_type_encoded'=>$type,'growth_stage'=>$farmer['growth_stage']??'vegetative','soil_type_encoded'=>$soil,'season_encoded'=>$season];
}
function advisory_message(string $class,array $f): string
{
    $messages=['irrigate_now'=>sprintf('High heat (%.1f°C), low humidity, and little rain are forecast. Check soil moisture and prioritize young or flowering crops if the root zone is dry.',$f['temp_c']),'delay_irrigation'=>'Rain is expected soon. Delay irrigation and check drainage before watering.','plant_now'=>'Conditions may suit planting. Check soil moisture and your local planting calendar before sowing.','delay_planting'=>'Heat or waterlogging risk may be high. Check soil and local field conditions before planting.','harvest_immediately'=>'Strong wind or storm risk may be high. Check vulnerable crops and secure stored produce if local conditions confirm the risk.','apply_fungicide'=>'Warm, humid conditions can favor fungal disease. Inspect plants and record symptoms; ask a local extension officer to confirm the cause before choosing any treatment.','protect_livestock'=>'Check suitable shelter, clean water, and ventilation for livestock during severe weather.','normal_operations'=>'No urgent weather action is indicated. Continue routine farm operations and monitor the local forecast.'];
    return $messages[$class]??$messages['normal_operations'];
}
function recommend_advisory(array $weather,array $farmer): array
{
    $features=advisory_features($weather,$farmer);$model=new MLRecommender();$result=$model->predict($features);$class=$result['class'];$confidence=(float)$result['confidence'];$fallback=$class===null||$confidence<ML_CONFIDENCE_THRESHOLD;
    if($fallback&&ML_FALLBACK_TO_RULES){$class=advisory_rule($features);$confidence=0.0;$source='rules_fallback';}else{$source=$result['source'];}
    $text=advisory_message($class,$features);
    try{$q=db()->prepare('INSERT INTO ml_predictions (farmer_id,weather_snapshot_json,input_features_json,predicted_class,confidence,recommendation_text,created_at) VALUES (?,?,?,?,?,?,NOW())');$q->execute([(string)($farmer['email']??$farmer['id']??'anonymous'),json_encode($weather,JSON_UNESCAPED_UNICODE),json_encode($features,JSON_UNESCAPED_UNICODE),$class,$confidence,$text]);}catch(Throwable $e){}
    return ['class'=>$class,'confidence'=>$confidence,'source'=>$source,'features'=>$features,'text'=>$text];
}
