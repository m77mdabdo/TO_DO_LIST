<?php 

namespace Classes\Validation;
use Classes\Validation\Validator ;
class Required implements Validator{
    public function check($key,$value){

        if(empty($value)){
            return "$key is required ";

        }else{
            return false;

        }

    }
}
