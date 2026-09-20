<?php
require_once __DIR__.'/includes/auth.php'; redirect_logged_in();
$errors=[]; $old=$_POST; if($_SERVER['REQUEST_METHOD']==='POST'){ $name=trim($_POST['full_name']??''); $email=strtolower(trim($_POST['email']??'')); $phone=preg_replace('/\D/','',$_POST['phone']??''); $password=$_POST['password']??''; $region=$_POST['region']??''; $farm=(float)($_POST['farm_size']??0); $crop=$_POST['crop_type']??''; $soil=$_POST['soil_quality']??''; $regions=['Hhohho','Manzini','Shiselweni','Lubombo']; $crops=['Maize','Sorghum','Beans','Sweet Potatoes','Cabbage','Groundnuts','Cassava','Citrus','Sugarcane','Cotton','Pineapple','Mango']; if($name==='')$errors[]='Full name is required.'; if(!filter_var($email,FILTER_VALIDATE_EMAIL))$errors[]='Enter a valid email.'; if(!preg_match('/^7[6-8]\d{6}$/',$phone))$errors[]='Use an Eswatini mobile number such as 76123456.'; if(strlen($password)<8)$errors[]='Password must be at least 8 characters.'; if($password!==($_POST['confirm_password']??''))$errors[]='Passwords do not match.'; if(!in_array($region,$regions,true))$errors[]='Choose your region.'; if($farm<.5||$farm>100)$errors[]='Farm size must be between 0.5 and 100 hectares.'; if(!in_array($crop,$crops,true)||!in_array($soil,['Poor','Moderate','Good','Excellent'],true))$errors[]='Complete your farm details.'; if(!isset($_POST['terms']))$errors[]='You must accept the terms.'; $all=users(); if(isset($all[$email]))$errors[]='An account with this email already exists.'; if(!$errors){$all[$email]=['name'=>htmlspecialchars($name,ENT_QUOTES,'UTF-8'),'email'=>$email,'phone'=>normalise_phone($phone),'password'=>password_hash($password,PASSWORD_DEFAULT),'region'=>$region,'farm_size'=>$farm,'crop_type'=>$crop,'soil_quality'=>soil_value($soil),'registered_at'=>date('Y-m-d H:i:s'),'last_login'=>null]; write_json(USERS_FILE,$all); flash('success','Account created. Sign in to view your first field briefing.'); header('Location:index.php'); exit;}}
$regions=['Hhohho','Manzini','Shiselweni','Lubombo']; 
$crops=['Maize','Sorghum','Beans','Sweet Potatoes','Cabbage','Groundnuts','Cassava','Citrus','Sugarcane','Cotton','Pineapple','Mango']; ?>
<!doctype html>
<html lang="en">
    <head>
        <?php include __DIR__.'/includes/head.php'; ?>
        <title>Kudla kwesive | Register</title>
    </head><body class="auth-page">
        <main class="auth-layout">
            <section class="auth-hero compact">
                <div class="flag-mark">🇸🇿</div>
                <p class="kicker">Join the network</p>
                <h1>Bring your field into focus.</h1>
                <p>Register once to receive advice shaped around your region, crop, soil, and farm size.</p>
            </section>
            <section class="auth-panel">
                <div class="brand">KUDLA <span>KWESIVE</span>
            </div><p class="kicker">Farmer registration</p>
            <h2>Create your profile</h2><?php if($errors): ?>
                <div class="alert error"><ul>
                    <?php foreach($errors as $error): ?>
                        <li><?=e($error)?>
                    </li><?php endforeach; ?></ul>
                </div><?php endif; ?>
                <form method="post" class="registration-form">
                    <label>Full name<input name="full_name" value="<?=e($old['full_name']??'')?>" required>
                </label><label>Email address<input type="email" name="email" value="<?=e($old['email']??'')?>" required></label>
                <label>Phone number <small>Eswatini mobile: +268 76XX XXXX</small>
                <input name="phone" inputmode="numeric" placeholder="76123456" value="<?=e($old['phone']??'')?>" required>
            </label><div class="form-grid">
                <label>Region<select name="region" required>
                    <option value="">Choose</option>
                    <?php foreach($regions as $value): ?>
                        <option <?=($old['region']??'')===$value?'selected':''?>><?=e($value)?>
                    </option><?php endforeach; ?></select></label><label>Farm size (ha)<input type="number" name="farm_size" min="0.5" max="100" step="0.1" value="<?=e($old['farm_size']??'')?>" required></label></div><div class="form-grid"><label>Primary crop<select name="crop_type" required><option value="">Choose</option><?php foreach($crops as $value): ?><option <?=($old['crop_type']??'')===$value?'selected':''?>><?=e($value)?></option><?php endforeach; ?></select></label><label>Soil quality<select name="soil_quality" required><option value="">Choose</option><?php foreach(['Poor','Moderate','Good','Excellent'] as $value): ?><option <?=($old['soil_quality']??'')===$value?'selected':''?>><?=e($value)?></option><?php endforeach; ?></select></label></div><div class="form-grid"><label>Password<input type="password" name="password" minlength="8" required></label><label>Confirm password<input type="password" name="confirm_password" minlength="8" required></label></div><label class="check"><input type="checkbox" name="terms" required> I agree to the Terms &amp; Conditions.</label><button class="button" type="submit">Create my account <span>→</span></button></form><p class="auth-switch">Already registered? <a href="index.php">Sign in</a></p></section></main></body></html>