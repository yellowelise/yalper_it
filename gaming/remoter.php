
<!doctype html>
<html lang="it">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
	<link rel="shortcut icon" href="../favicons/favicon.ico">
	  <link rel="icon" type="image/png" sizes="32x32" href="../favicons/favicon32.png">
	  <link rel="icon" type="image/png" sizes="16x16" href="../favicons/favicon16.png">
		<link rel="manifest" href="manifest.json?123456" />

	  <!-- Apple -->
	  <meta name="apple-mobile-web-app-title" content="YalpeR - Live Replay System">
	<meta name="google-adsense-account" content="ca-pub-9880317414365518">
	  <link rel="apple-touch-icon" sizes="180x180" href="../favicons/favicon180.png">
	<script  src="../js/jquery-3.6.4.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <!--link href="node_modules/video.js/dist/video-js.min.css" rel="stylesheet">
	<script src="node_modules/video.js/dist/video.js"></script-->
	<!--script src="node_modules/videojs-vjsdownload/dist/videojs-vjsdownload.js"></script>
    <link href="node_modules/videojs-vjsdownload/dist/videojs-vjsdownload.css" rel="stylesheet"-->

    
			  
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet"  crossorigin="anonymous">


    
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=Titillium+Web&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/styles.css?tagdsok" type="text/css">
	<script src="https://js.pusher.com/8.2.0/pusher.min.js"></script>
    <script src="../public-config.js"></script>
    <title>YalpeR - Live Replay System</title>
    
    
    </head>

<body style="">
	


<!-- save_event.html -->
<div class="container">
	<h2>Evento</h2>
    <input type="text" id="event_push" class="form-control" placeholder="Inserisci Evento">
	<hr />
	<div class="row" style="margin-bottom:20px;">
		<div class="col-md-4">
			<a href="javascript:;" class="btn btn-xl btn-success" style="width:100%;height:25vh;font-size:3rem;margin-bottom:5px;" onclick=send("Gol-noi")>Goal Nostro!</a>
		</div>
		<div class="col-md-4">
			<a href="javascript:;" class="btn btn-xl btn-success" style="width:100%;height:25vh;font-size:3rem;margin-bottom:5px;" onclick=send("Occasione-noi")>Occasione Nostra!</a>
		</div>
		<div class="col-md-4">
			<a href="javascript:;" class="btn btn-xl btn-success" style="width:100%;height:25vh;font-size:3rem;margin-bottom:5px;" onclick=send("Parata-noi")>Parata Nostra!</a>
		</div>
	</div>

	<div class="row">
		<div class="col-md-4">
			<a href="javascript:;" class="btn btn-xl btn-danger" style="width:100%;height:25vh;font-size:3rem;margin-bottom:5px;" onclick=send("Gol-avversari")>Goal Avversari!</a>
		</div>
		<div class="col-md-4">
			<a href="javascript:;" class="btn btn-xl btn-danger" style="width:100%;height:25vh;font-size:3rem;margin-bottom:5px;" onclick=send("Occasione-avversari")>Occasione Avversari!</a>
		</div>
		<div class="col-md-4">
			<a href="javascript:;" class="btn btn-xl btn-danger" style="width:100%;height:25vh;font-size:3rem;margin-bottom:5px;" onclick=send("Parata-avversari")>Parata Avversari!</a>
		</div>
	</div>

</div>
</body>
<script src="https://js.pusher.com/8.2.0/pusher.min.js"></script>
<script>
    // Configurazione Pusher
    const publicConfig = window.YALPER_PUBLIC_CONFIG || { pusherKey: '2da066d21277e3a81c67', pusherCluster: 'eu' };
    const pusher = new Pusher(publicConfig.pusherKey, {
        cluster: publicConfig.pusherCluster
    });


function send(tag)
{
        const eventPush = document.getElementById('event_push').value;
        fetch('trigger_save.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                event_push: eventPush,
                tag: tag
                
            })
        });

}


</script>
