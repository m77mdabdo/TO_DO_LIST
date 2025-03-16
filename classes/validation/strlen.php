<?php 

namespace Classes\Validation;
use Classes\Validation\Validator ;
 
class Strlen implements Validator{
    public function check($key, $value) {
        if(strlen($value)<3){
            return "$key must be at least 3 characters";
        }else{
            return false;
        }
    }
}