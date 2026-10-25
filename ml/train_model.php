<?php
require_once __DIR__.'/generate_training_data.php';
require_once __DIR__.'/../includes/database.php';
require_once __DIR__.'/../vendor/autoload.php';

use Rubix\ML\Classifiers\RandomForest;
use Rubix\ML\Datasets\Labeled;
use Rubix\ML\Persisters\Filesystem;
use Rubix\ML\PersistentModel;

$csv=generate_training_csv((int)($argv[1]??1200)); $file=fopen($csv,'rb'); $header=fgetcsv($file); $samples=[];$labels=[];
while(($row=fgetcsv($file))!==false){$values=array_combine($header,$row);$labels[]=$values['label_class'];unset($values['label_class']);$samples[]=array_map(static function($v){return is_numeric($v)?(float)$v:$v;},$values);}
fclose($file);
$indices=range(0,count($samples)-1);shuffle($indices);$split=(int)(count($indices)*0.8);$train=array_slice($indices,0,$split);$test=array_slice($indices,$split);
$trainSamples=array_map(fn($i)=>$samples[$i],$train);$trainLabels=array_map(fn($i)=>$labels[$i],$train);
$testSamples=array_map(fn($i)=>$samples[$i],$test);$testLabels=array_map(fn($i)=>$labels[$i],$test);
$model=new PersistentModel(new RandomForest(null,100),new Filesystem(ML_MODEL_PATH));$model->train(Labeled::build($trainSamples,$trainLabels));
$predictions=$model->predict(Labeled::build($testSamples,$testLabels));$correct=0;$matrix=[];
foreach($testLabels as $i=>$actual){$pred=$predictions[$i];$matrix[$actual][$pred]=($matrix[$actual][$pred]??0)+1;if($actual===$pred)$correct++;}
$accuracy=$correct/count($testLabels);$modelDir=__DIR__.'/models';if(!is_dir($modelDir))mkdir($modelDir,0755,true);
$model->save();
try{$q=db()->prepare('UPDATE ml_models SET is_active=0');$q->execute();$q=db()->prepare('INSERT INTO ml_models (model_name,model_path,accuracy_score,trained_at,is_active) VALUES (?,?,?,?,1)');$q->execute(['Rubix Random Forest',ML_MODEL_PATH,$accuracy,date('Y-m-d H:i:s')]);}catch(Throwable $e){}
echo 'Holdout accuracy: '.number_format($accuracy*100,2)."%\nConfusion matrix (actual => predicted counts):\n".json_encode($matrix,JSON_PRETTY_PRINT)."\n";
$sample=['temp_c'=>34.0,'humidity_pct'=>25,'rain_probability'=>0.05,'wind_kmh'=>12.0,'cloud_pct'=>0,'condition_encoded'=>'clear','farmer_type_encoded'=>'crop','growth_stage'=>'flowering','soil_type_encoded'=>'loamy','season_encoded'=>'dry'];
$sampleDataset=Rubix\ML\Datasets\Unlabeled::build([array_values($sample)]);$sampleClass=$model->predict($sampleDataset)[0];$sampleProb=method_exists($model,'proba')?($model->proba($sampleDataset)[0][$sampleClass]??null):null;
echo "Sample input: ".json_encode($sample)."\nSample prediction: {$sampleClass}; confidence ".($sampleProb===null?'unavailable':number_format((float)$sampleProb*100,2).'%')."\n";
