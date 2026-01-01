<?php

use Classes\Session;
require_once '../App.php';

if ($request->check($request->get("status")) && $request->check($request->get("id"))) {
    $id = $request->get("id");
    $status = $request->get("status");
     
    $result= $conn->prepare("select titile from todo where id=:id");
    $result->bindParam(":id",$id,PDO::PARAM_INT);
    $result->execute();
    if($result->rowCount()==1){

        $updateQuery = $conn->prepare("UPDATE todo SET status = :status WHERE id = :id");
        $updateQuery->bindParam(":status", $status, PDO::PARAM_STR);
        $updateQuery->bindParam(":id", $id, PDO::PARAM_INT);
        $result = $updateQuery->execute();

        if ($result) {
            Session::set("success", "status updated successfully");
            header("location: ../index.php");
            exit();
        } else {
            Session::set("errors", ["Error while updating"]);
            header("location: ../edit.php?id=$id");
            exit();
        }

    }else{
        Session::set("errors",["todo not found"]);
        $request->redirect("../index.php");
    }

    
}else{
    $request->redirect("../indec.php");
}