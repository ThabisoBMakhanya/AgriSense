<?php
require_once __DIR__.'/generate_training_data.php';

final class MLRecommender
{
    private $model;
    public function __construct(?string $path=null)
    {
        $path=$path??ML_MODEL_PATH;
        if(!is_file($path)||!is_file(__DIR__.'/../vendor/autoload.php')) return;
        require_once __DIR__.'/../vendor/autoload.php';
        try{$this->model=Rubix\ML\PersistentModel::load(new Rubix\ML\Persisters\Filesystem($path));}catch(Throwable $e){$this->model=null;}
    }
    public function predict(array $features): array
    {
        $input=[];foreach(ML_FEATURES as $key){if(!array_key_exists($key,$features))throw new InvalidArgumentException("Missing ML feature: {$key}");$input[$key]=$features[$key];}
        if(!$this->model)return ['class'=>null,'confidence'=>0.0,'source'=>'fallback','reason'=>'Model unavailable'];
        $dataset=Rubix\ML\Datasets\Unlabeled::build([array_values($input)]);
        $class=$this->model->predict($dataset)[0];$confidence=0.0;
        if(method_exists($this->model,'proba')){$probabilities=$this->model->proba($dataset)[0]??[];$confidence=(float)($probabilities[$class]??(is_numeric($probabilities)?max($probabilities):0));}
        return ['class'=>$class,'confidence'=>$confidence,'source'=>'rubix'];
    }
}
