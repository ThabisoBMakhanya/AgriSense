<?php
require_once __DIR__ . '/../includes/config.php';

const ADVISORY_CLASSES = ['irrigate_now','delay_irrigation','plant_now','delay_planting','harvest_immediately','apply_fungicide','protect_livestock','normal_operations'];
const ML_FEATURES = ['temp_c','humidity_pct','rain_probability','wind_kmh','cloud_pct','condition_encoded','farmer_type_encoded','growth_stage','soil_type_encoded','season_encoded'];

function advisory_rule(array $x): string
{
    if (($x['condition_encoded']==='storm' || $x['wind_kmh']>55) && $x['farmer_type_encoded']!=='crop') return 'protect_livestock';
    if (($x['condition_encoded']==='storm' || ($x['rain_probability']>0.7 && $x['wind_kmh']>40)) && $x['growth_stage']==='harvesting') return 'harvest_immediately';
    if ($x['temp_c']>38 && in_array($x['farmer_type_encoded'],['livestock','poultry'],true)) return 'protect_livestock';
    if ($x['temp_c']>38 || ($x['temp_c']>35 && $x['rain_probability']>0.75) || ($x['rain_probability']>0.9 && $x['soil_type_encoded']==='clay')) return 'delay_planting';
    if ($x['humidity_pct']>85 && $x['temp_c']>=20 && $x['temp_c']<=30 && $x['farmer_type_encoded']==='crop') return 'apply_fungicide';
    if ($x['season_encoded']==='rainy' && $x['rain_probability']>0.5 && $x['farmer_type_encoded']==='crop') return 'delay_irrigation';
    if ($x['growth_stage']==='planting' && $x['season_encoded']==='rainy' && $x['temp_c']>=18 && $x['temp_c']<=32 && $x['rain_probability']>=0.25 && $x['rain_probability']<=0.7 && $x['farmer_type_encoded']==='crop') return 'plant_now';
    if ($x['temp_c']>=33 && $x['humidity_pct']<35 && $x['rain_probability']<0.1 && $x['farmer_type_encoded']==='crop') return 'irrigate_now';
    return 'normal_operations';
}

function synthetic_row(): array
{
    $season = ['dry','rainy','harmattan'][random_int(0,2)];
    $ranges = ['dry'=>[18,40,10,65], 'rainy'=>[17,34,40,100], 'harmattan'=>[12,31,5,55]][$season];
    $temperature = random_int($ranges[0]*10,$ranges[1]*10)/10;
    $humidity = random_int($ranges[2],$ranges[3]);
    $rain = random_int(0,1000)/1000;
    $wind = random_int(0,650)/10;
    $condition = $wind>50 && $rain>0.65 ? 'storm' : ($rain>0.65 ? 'rain' : (random_int(0,100)<55?'clear':'cloudy'));
    $types=['crop','livestock','poultry','fisheries','beekeeping'];
    $stages=['planting','vegetative','flowering','harvesting'];
    $x=['temp_c'=>$temperature,'humidity_pct'=>$humidity,'rain_probability'=>$rain,'wind_kmh'=>$wind,'cloud_pct'=>random_int(0,100),'condition_encoded'=>$condition,'farmer_type_encoded'=>$types[array_rand($types)],'growth_stage'=>$stages[array_rand($stages)],'soil_type_encoded'=>['sandy','loamy','clay'][random_int(0,2)],'season_encoded'=>$season];
    switch(random_int(0,7)){
        case 0:$x=array_merge($x,['temp_c'=>random_int(330,390)/10,'humidity_pct'=>random_int(12,34),'rain_probability'=>random_int(0,90)/1000,'wind_kmh'=>random_int(0,280)/10,'farmer_type_encoded'=>'crop']);break;
        case 1:$x=array_merge($x,['temp_c'=>random_int(18,32),'humidity_pct'=>random_int(45,85),'rain_probability'=>random_int(55,72)/100,'season_encoded'=>'rainy','farmer_type_encoded'=>'crop']);break;
        case 2:$x=array_merge($x,['temp_c'=>random_int(200,300)/10,'humidity_pct'=>random_int(55,82),'rain_probability'=>random_int(25,49)/100,'season_encoded'=>'rainy','growth_stage'=>'planting','farmer_type_encoded'=>'crop']);break;
        case 3:$x=array_merge($x,['temp_c'=>random_int(39,450)/10,'humidity_pct'=>random_int(20,90),'rain_probability'=>random_int(0,90)/100,'growth_stage'=>'planting','farmer_type_encoded'=>'crop']);break;
        case 4:$x=array_merge($x,['temp_c'=>random_int(18,33),'humidity_pct'=>random_int(25,75),'rain_probability'=>random_int(72,98)/100,'wind_kmh'=>random_int(410,650)/10,'condition_encoded'=>'storm','growth_stage'=>'harvesting','farmer_type_encoded'=>'crop']);break;
        case 5:$x=array_merge($x,['temp_c'=>random_int(200,300)/10,'humidity_pct'=>random_int(86,100),'rain_probability'=>random_int(10,45)/100,'farmer_type_encoded'=>'crop']);break;
        case 6:$x=array_merge($x,['temp_c'=>random_int(390,440)/10,'humidity_pct'=>random_int(15,100),'rain_probability'=>random_int(10,90)/100,'wind_kmh'=>random_int(0,650)/10,'farmer_type_encoded'=>'livestock']);break;
        default:$x=array_merge($x,['temp_c'=>random_int(18,320)/10,'humidity_pct'=>random_int(40,80),'rain_probability'=>random_int(10,40)/100,'wind_kmh'=>random_int(0,300)/10,'condition_encoded'=>'clear','farmer_type_encoded'=>'beekeeping','growth_stage'=>'vegetative','season_encoded'=>'harmattan']);
    }
    $label = advisory_rule($x);
    if (random_int(1,100)<=12) $label=ADVISORY_CLASSES[array_rand(ADVISORY_CLASSES)];
    return [$x,$label];
}

function generate_training_csv(int $count=1200): string
{
    $dir=__DIR__.'/data'; if(!is_dir($dir)) mkdir($dir,0755,true);
    $path=$dir.'/training_data.csv'; $handle=fopen($path,'wb');
    fputcsv($handle,array_merge(ML_FEATURES,['label_class']));
    for($i=0;$i<$count;$i++){[$x,$label]=synthetic_row();fputcsv($handle,array_merge(array_values($x),[$label]));}
    fclose($handle); return $path;
}

if (PHP_SAPI==='cli' && realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__) {
    $path=generate_training_csv((int)($argv[1]??1200));
    fwrite(STDOUT,"Generated 1200 synthetic examples at {$path}\n");
}
