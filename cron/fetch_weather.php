<?php
require_once __DIR__.'/../includes/config.php';require_once __DIR__.'/../modules/weather_service.php';
foreach(['Hhohho','Manzini','Shiselweni','Lubombo'] as $region){try{$d=fetch_weather_location($region,true);echo $d['location'].': updated'.PHP_EOL;}catch(Throwable $e){fwrite(STDERR,$region.': '.$e->getMessage().PHP_EOL);}}
