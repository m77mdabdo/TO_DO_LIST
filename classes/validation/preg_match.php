<?php 

namespace Classes\Validation;
use Classes\Validation\Validator ;
 
class Preg_match implements Validator{
    public function check($key, $value) {
        if(preg_match('/[^a-zA-Z0-9\s]/',$value)){
            return "$key title must not contain special characters ";
        }else{
            return false;
        }
    }
}