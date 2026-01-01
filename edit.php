<?php
require_once 'inc/header.php';
?>


<?php 
require_once 'App.php';


//id url
if($request->check($request->get("id"))){
    $id=$request->get("id");
}else{
    $request->redirect("404,php");
}

   $request= $conn->prepare("select `titile` from todo  where id=:id");
   $request->bindParam(":id",$id,PDO::PARAM_INT);
   $request->execute();
   $titile= $request->fetch(PDO::FETCH_ASSOC);

   if(!count($titile)>0){
    $message = "not found";
    
   }




?>
 <?php 
                    
          use Classes\Session ;          
                    if(Session::get("errors")){
                        foreach( Session::get("errors") as $error){

                       
                        
                        ?>
              
                    <div class="alert alert-danger"> <?php 
                    // foreach($error as $erro)
                    //   if(is_array($erro)){
                    //     foreach($erro as $err){
                    //         echo $err ;
                    //     }
                    //   }
                    echo $error ;
                   
                     
                    
                     ?></div>
                    
                  <?php  } }
                    Session::unset("errors");
                    
                    ?>

<body class="container w-50 mt-5">
    <form action="handle/edit.php?id= <?php echo $id?>" method="post">
            <textarea type="text" class="form-control"  name="titile" id="" placeholder="enter your note here"> <?php  echo $titile ["titile"]?></textarea>
            <div class="text-center">
                <button type="submit" name="submit" class="form-control text-white bg-info mt-3 " >Update</button>
            </div>  
    </form>
</body>
</html>