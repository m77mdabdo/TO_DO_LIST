<?php

require_once 'classes/Request.php';
require_once 'classes/sessionClass.php';
require_once 'classes/validation/validator.php';
require_once 'classes/validation/required.php' ;
require_once 'classes/validation/preg_match.php' ;
require_once 'classes/validation/str.php';
require_once 'classes/validation/strlen.php' ;
require_once 'classes/validation/validation.php';
require_once 'inc/connection.php';



use Classes\Request;
use classes\Session;
use Classes\Validation\Preg_match;
use Classes\Validation\Required ;
use Classes\Validation\Str;
use Classes\Validation\Strlen ;
use Classes\Validation\Validation;
// use Classes\Validation\Validator ;


$request= new Request ;
$session =new Session;
$preg=new Preg_match ;
$required= new Required;
$str= new str ;
$strlen= new Strlen ;
$validation = new Validation;






