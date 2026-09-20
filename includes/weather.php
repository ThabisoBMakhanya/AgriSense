<?php
require_once __DIR__ . '/config.php';
function region_weather(string $region): array {
    $places=['Hhohho'=>['Mbabane',-26.3054,31.1367],'Manzini'=>['Manzini',-26.4988,31.3800],'Shiselweni'=>['Nhlangano',-27.1122,31.1989],'Lubombo'=>['Siteki',-26.4525,31.9472]];
    $place=$places[$region]??$places['Manzini']; $cache=read_json(WEATHER_CACHE_FILE,[]); $key=$region;
    if (isset($cache[$key]) && time()-($cache[$key]['cached_at']??0)<WEATHER_CACHE_SECONDS) return $cache[$key]['data'];
    $data=null;
    if (WEATHER_API_KEY !== '') { $url='https://api.openweathermap.org/data/2.5/weather?lat='.$place[1].'&lon='.$place[2].'&units=metric&appid='.urlencode(WEATHER_API_KEY); $raw=@file_get_contents($url); $api=$raw?json_decode($raw,true):null; if (is_array($api) && isset($api['main'])) $data=['location'=>$place[0],'temperature'=>round($api['main']['temp'],1),'humidity'=>(int)$api['main']['humidity'],'rainfall'=>round((float)($api['rain']['1h']??0),1),'wind_speed'=>round((float)($api['wind']['speed']??0),1),'condition'=>$api['weather'][0]['main']??'Clear','icon'=>'☀️']; }
    if (!$data) { $month=(int)date('n'); $wet=$month>=10 || $month<=3; $data=['location'=>$place[0],'temperature'=>$wet?26.4:23.1,'humidity'=>$wet?68:47,'rainfall'=>$wet?8.6:1.2,'wind_speed'=>4.8,'condition'=>$wet?'Clouds':'Clear','icon'=>$wet?'🌦️':'☀️']; }
    $cache[$key]=['cached_at'=>time(),'data'=>$data]; write_json(WEATHER_CACHE_FILE,$cache); return $data;
}
