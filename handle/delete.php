<?php

use Classes\Session;

require_once '../App.php' ;

if($request->check($request->get("id"))){
    $id=$request->get("id");
    $result=$conn->prepare("select `titile` from todo where id=:id");
    $result->bindParam(":id",$id,PDO::PARAM_INT);
    $result->execute();
    $titile = $result->fetch(PDO::FETCH_ASSOC);

    if(count($titile)>0){
        $result=$conn->prepare("delete from todo where id=:id");
        $result->bindParam(":id",$id,PDO::PARAM_INT);
        $out = $result->execute();

        if($out){
            Session::set("success","deleted successfly");
            $request ->redirect("../index.php");

        }
      
    

    }else{
        Session::set("errors",["not found"]);
        $request->redirect("../index.php");
    }
}else{
    $request->redirect("../index.php");
}