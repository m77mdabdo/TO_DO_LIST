<?php



require_once '../App.php';
require_once '../inc/connection.php';
use Classes\Session;

//check to submit 

if($request->check($request->post('submit'))){
 //catch data 

 $titile=$request->filter($request->post("titile"));
 //valition 
 $validation->validate("titile",$titile,['Required','Str','Strlen','Preg_match']);
 $errors = $validation->getError();
 if (!empty($errors)) { 
     Session::set("errors", $errors);
     $request->redirect("../index.php");
     exit(); 
 }
  else{

 
    //  insert
   
    // $out=$conn->query("insert into `todo`(`titile`,`user_id`)values ('$titile','user_id=$user_id')");
    $query=$conn->prepare("INSERT INTO `todo`( `titile` ) VALUES (:titile) ");

    $query->bindParam(":titile",$titile,PDO::PARAM_STR);
    
    if ($query->execute()) {
        Session::set("success", "Task created successfully!");
    } else {
        Session::set("errors", ["Error while inserting."]);
    }
    $request->redirect("../index.php");

    

//  session success -> index 
  }



 

 
  
}else{
    $request->redirect("../index.php");
}
