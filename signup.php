<?php


 include('./controllers/register.php'); 



?>
<!doctype html>
<html lang="it">
  <head>
    <!-- Required meta tags -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
	<link rel="shortcut icon" href="favicons/favicon.ico">
	  <link rel="icon" type="image/png" sizes="32x32" href="favicons/favicon32.png">
	  <link rel="icon" type="image/png" sizes="16x16" href="favicons/favicon16.png">
		<link rel="manifest" href="manifest.json" />

	  <!-- Apple -->
	  <meta name="apple-mobile-web-app-title" content="YalpeR - Live Replay System">
	
	  <link rel="apple-touch-icon" sizes="180x180" href="favicons/favicon180.png">
<script
			  src="https://code.jquery.com/jquery-3.6.4.min.js"
			  integrity="sha256-oP6HI9z1XaZNBrJURtCoUT5SUnxFr8s3BzRl+cbzUq8="
			  crossorigin="anonymous"></script>
			  
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous">
    <link rel="stylesheet" href="css/styles.css" type="text/css">
    <title>YalpeR - Live Replay System</title>
    <!-- jQuery + Bootstrap JS -->
    <!--script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script-->
    <!--script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/js/bootstrap.min.js"></script-->
<script
			  src="https://code.jquery.com/jquery-3.6.4.min.js"
			  integrity="sha256-oP6HI9z1XaZNBrJURtCoUT5SUnxFr8s3BzRl+cbzUq8="
			  crossorigin="anonymous"></script>    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous">
        <link rel="stylesheet" href="css/styles.css?fdsfds" type="text/css">

</head>
<body>
   
   <?php //include('./header.php'); ?>
    <div class="row justify-content-center">
            <div class="col-sm-8 col-xs-12 col-md-6" style="margin: auto;background: #ffffff;box-shadow: 0px 14px 80px rgba(34, 35, 58, 0.2);padding: 40px 55px 45px 55px;transition: all .3s;border-radius: 20px;">
				<h4>Closed Beta, mancano solo <?php echo "<b>" . $restanti . "</b>";?> posti disponibili</h4>
				<?php
				if ($restanti > 0)
				{?>
                
                <?php
                }
                else
                {
					echo "<h6>Nessun posto disponibile, manda una mail a <a href='mailto:info@yalper.it'>info@yalper.it</a> per la lista di attesa</h6>";
				}
				?>
                <br /><a href="login.php" class="btn btn-outline-danger btn-lg btn-block">Vai al Login</a>

            </div>
        </div>
</body>
</html>
