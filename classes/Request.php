<?php 

namespace classes;

class Request {

    // get 
    public function get($key){
        if(isset($_GET[$key])){
            return $_GET[$key];
        }else{
            return "key not found";
        }
    }

    public function post($key){
        if(isset( $_POST[$key])){
            return $_POST[$key];
        }else{
            return false;
        }
    }
     //catch data 
    public function filter($key){
        return trim(htmlspecialchars($key));
    }

    //check 

    public function check($key){
        return isset($key);
    }

    public function redirect($path){
        header( "location:$path");
    }

    

    
}