<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="-1">
    <!-- The above 3 meta tags *must* come first in the head; any other head content must come *after* these tags -->
    <title>Web Works CMS</title>

    <!-- Bootstrap -->
    <link href="<?php echo $BASE; ?>/cms.php?css=bootstrap.min.css" rel="stylesheet">
    <link href="<?php echo $BASE; ?>/cms.php?css=theme" rel="stylesheet">
  
    <style>
      .form {
        background-color: #fff;
        width: 400px;

        margin: auto;
        margin-top: 20vh;
        padding: 10px;
        -webkit-border-radius: 5px;
        -moz-border-radius: 5px;
        border-radius: 5px;
      }



      .form h1 {
        display: block;
        font-size: 18px;
        padding: 0 0 10px 0;
        text-align: center;
        color: #000;
      }

      .form h2 {
        margin: 0 0 20px 0;padding:0 0 10px 0;
        font-size: 20px;
        border-bottom: 1px solid #ccc;
      }

      .form form label {
        color: #000;
      }

      .form form button {
        width: 100%;
        font-weight: bold;
      }

    </style>

    <!-- HTML5 shim and Respond.js for IE8 support of HTML5 elements and media queries -->
    <!-- WARNING: Respond.js doesn't work if you view the page via file:// -->
    <!--[if lt IE 9]>
      <script src="https://oss.maxcdn.com/html5shiv/3.7.2/html5shiv.min.js"></script>
      <script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
    <![endif]-->
  </head>
  <body>

    
    <div class="form">
      <img width="100%" src="<?php echo $BASE; ?>/cms.php?img=logo" alt="Web Works CMS Login">
      
      <p>
        <h2>Step 1: Create necessary files</h2>
        Linux User to create files as: <strong><?php echo $linuxuser; ?></strong>
      </p>

      <form autocomplete="off" method="post" action="<?php echo $BASE; ?>/cms.php?step=2">
        <div class="form-group">
          <input id="pass" name="password" type="password" class="form-control" placeholder="Root Password" value="<?php echo $POST['password']; ?>">
        </div>

        <div class="row">
          <div class="col-xs-12">
            <button type="submit" class="btn btn-default">Next</button>
          </div>
        </div>
         
      </form>
    </div>
    
    <script>

    </script>

  </body>
</html>