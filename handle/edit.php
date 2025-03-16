<?php

use Classes\Session;
require_once '../App.php';

if ($request->check($request->post("submit")) && $request->check($request->get("id"))) {
    $id = $request->get("id");
    $titile = $request->filter($request->post("titile"));

    
    $validation->validate("titile", $titile, ['Required', 'Str', 'Strlen', 'Preg_match']);
    $errors = $validation->getError();


    if (empty($errors)) {
        
        $query = $conn->prepare("SELECT `titile` FROM todo WHERE id = :id");
        $query->bindParam(":id", $id, PDO::PARAM_INT);
        $query->execute();
        $out = $query->fetch(PDO::FETCH_ASSOC);

        if ($out) { 
           
            $updateQuery = $conn->prepare("UPDATE todo SET titile = :titile WHERE id = :id");
            $updateQuery->bindParam(":titile", $titile, PDO::PARAM_STR);
            $updateQuery->bindParam(":id", $id, PDO::PARAM_INT);
            $result = $updateQuery->execute();

            if ($result) {
                Session::set("success", "Title updated successfully");
                header("location: ../index.php");
                exit();
            } else {
                Session::set("errors", ["Error while updating"]);
                header("location: ../edit.php?id=$id");
                exit();
            }
        } else {
            Session::set("errors", ["Title not found"]);
            header("location: ../index.php");
            exit();
        }
    } else {
        
        Session::set("errors", $errors);
        header("location: ../edit.php?id=$id");
        exit();
    }
} else {
    $request->redirect("../404.php");
}
