<?php 
namespace Classes;

class Session {

    // start session 

    public function __construct() {

        session_start();

    }
    //set session 

    public static function set($key,$value){
        $_SESSION [$key]=[$value];
    }
    // get 

    public static function get($key){
        if(isset($_SESSION[$key])){

            return $_SESSION [$key];
        }else{
            return false;
        }
       

    }
    //unset 

    public  static function unset($key){
        unset($_SESSION[$key]);
    }
    // destroy

    public function destroy(){
        session_destroy();
    }
    

} 