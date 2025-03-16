<?php 

namespace Classes\Validation;


class Validation {

    private $errors =[];
    public  function validate($key, $value ,$roles){
        foreach ($roles as $role) {
            if (!is_string($role)) {
                $this->errors[$key][] = "Invalid validation rule format.";
                continue;
            }
            
            $roleClass = "Classes\\Validation\\" . ucfirst($role); // تأكد من صحة اسم الكلاس
            
            if (!class_exists($roleClass)) {
                $this->errors[$key][] = "Validation rule '{$roleClass}' does not exist.";
                continue;
            }
            
            $object = new $roleClass();
            $out = $object->check($key, $value);
            
            if ($out !== false) {
                $this->errors[$key][] = $out;
            }
        }
        
    }

    public function getError(){
        return !empty($this->errors) ? $this->errors : false;
    }
}