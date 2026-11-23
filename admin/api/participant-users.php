<?php
require_once __DIR__.'/../../api/app/controller/ParticipantUserController.php';
$admin=(new AdminController())->requireSuperAdmin();
try{
    verify_csrf();$c=new ParticipantUserController();$action=(string)($_POST['action']??'');
    if($action==='create')$c->create($admin,$_POST['issued_id']??'',$_POST['username']??'',$_POST['password']??'',$_POST['email']??'');
    elseif($action==='assign_username')$c->assignUsername($admin,(int)($_POST['user_id']??0),$_POST['username']??'');
    else throw new Exception(t('invalid_action'));
    $_SESSION['admin_flash_success']='هەژماری User بە سەرکەوتوویی نوێکرایەوە.';
}catch(Throwable $e){$_SESSION['admin_flash_error']=$e instanceof mysqli_sql_exception?t('admin_database_error').' ('.$e->getCode().')':$e->getMessage();}
header('Location: ../participant-users.php');exit;
