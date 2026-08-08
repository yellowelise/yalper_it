<?php
session_start();
$event_push = $_REQUEST['event'];
/*
function generateRandomString($length = 10) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[random_int(0, $charactersLength - 1)];
    }
    return $randomString;
}
*/


if (!isset($_SESSION['logged']))
	header("location: login.php");

include('config/db.php');

$sqlQuery = mysqli_query($connection, "SELECT * FROM user_credits WHERE user_token = '".$_SESSION['token']."'");
$countRow = mysqli_num_rows($sqlQuery);
if($countRow == 1){
	while($rowData = mysqli_fetch_array($sqlQuery)){
		$credits_left = $rowData['left_credits'];
		$credits_used = $rowData['used_credits'];
	}
}	


  require __DIR__ . '/config/pusher.php';
  $pusher = yalper_create_pusher();


  $data['message'] = "Anche " . $_SESSION['firstname'] . " sta usando YalpeR!";
  $pusher->trigger('yalper', 'yalper-presence', $data);
  
 	$div = "6";
	if (isset($_REQUEST['div']))
		$div = $_REQUEST['div'];
		 
?>
<!doctype html>
<html lang="it">
  <head>
    <!-- Required meta tags -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
	<link rel="shortcut icon" href="favicons/favicon.ico">
	  <link rel="icon" type="image/png" sizes="32x32" href="favicons/favicon32.png">
	  <link rel="icon" type="image/png" sizes="16x16" href="favicons/favicon16.png">
		<link rel="manifest" href="manifest.json?123456" />

	  <!-- Apple -->
	  <meta name="apple-mobile-web-app-title" content="YalpeR - Live Replay System">
	
	  <link rel="apple-touch-icon" sizes="180x180" href="favicons/favicon180.png">
	<script  src="js/jquery-3.6.4.min.js"></script>
    <!--link href="node_modules/video.js/dist/video-js.min.css" rel="stylesheet">
	<script src="node_modules/video.js/dist/video.js"></script-->
	<!--script src="node_modules/videojs-vjsdownload/dist/videojs-vjsdownload.js"></script>
    <link href="node_modules/videojs-vjsdownload/dist/videojs-vjsdownload.css" rel="stylesheet"-->

<!-- unpkg : use the latest version of Video.js -->
	<link href="https://unpkg.com/video.js/dist/video-js.min.css" rel="stylesheet">
	<script src="https://unpkg.com/video.js/dist/video.min.js"></script>
	

    <!-- Bootstrap CSS -->
    <link href="css/bootstrap.min.css" rel="stylesheet">
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=Titillium+Web&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/styles.css?tagsok" type="text/css">
	<script src="https://js.pusher.com/8.2.0/pusher.min.js"></script>
    <script id="yalper" src="js/yalper_nocompr.js?<?php echo rand(10000,203939)?>" data-event="<?php echo $event_push?>"></script>
    <script src="js/base64.js"></script>
    <title>YalpeR - Live Replay System</title>
    <style>
      .liveStream80 {
					position: sticky; 
					right: 0; bottom: 0;
					height: 100%;
					width: 177.7777vh; /* 100 * 16 / 9 */
					min-width: 100%;
					min-height: 56.25vw; /* 100 * 9 / 16 */
					z-index: -100;
				}
      .liveStream {
					position: sticky; 
					right: 0; bottom: 0;
					height: 100%;
					width: 177.77777778vh; /* 100 * 16 / 9 */
					min-width: 100%;
					min-height: 56.25vw; /* 100 * 9 / 16 */
					z-index: -100;
				}
	.vertical-scrollable {
            position: absolute;
            top: 40px;
            bottom: 40px;
            left: 10px;
            overflow-y: scroll;
        }				
	.a_destra {
            position: absolute;
            top: 40px;
            bottom: 40px;
            right: 0px;
        }	

video {
	background: url('favicons/512.png') 50% 50% / contain no-repeat ;	
  object-fit: fill;
}  			
    </style>
</head>

<body style="background-color:#999;padding:0px !important;overflow:hidden;overflow-y: scroll;overscroll-behavior: contain;">
	

  <div  id="replay_div" style="display:block;">
	<div class="row">
		<div class="col-12" id="replay_btn">
		</div>	
	</div>	
  </div>
	
	<div class="liveStream80" style="display:none;z-index:30000;" id="player_modal">
	  <!--video id="player" class="video-js vjs-16-9"  style='width:100%;border:2px solid #22F;' controls preload="auto" width="500" height="300" data-setup='{ "poster": "favicons/512.png" }'-->
	  <video id="player" class="video-js vjs-16-9"  style='width:100%;border:2px solid #22F;' controls preload="auto" width="500" height="300" poster="favicons/512.png">
			<p class="vjs-no-js">To view this video please enable JavaScript, and consider upgrading to a web browser that <a href="https://videojs.com/html5-video-support/" target="_blank">supports HTML5 video</a></p>
	  </video>
	</div>	
		<button onclick=chiudiplayer() style="position:absolute;right: 0; top: 0;background: #bb2d3b;color: #fff;display:none;z-index:30001;" id="chiudi_player">CHIUDI<br />Video</button>

		<button onclick=delete_video() style="position:absolute;right: 25%; bottom: 0;background: #bb2d3b;color: #fff;display:none;z-index:30001;" id="elimina_video">Elimina<br />Selezionati</button>
		<button onclick=onShare() style="position:absolute;left: 0; bottom: 0;display:none;z-index:30001;" id="condividi_file_singolo">Condividi<br />Video</button>
		<button onclick=onShare() style="position:absolute;left: 0; bottom: 0;display:none;z-index:30001;" id="condividi_files_multipli">Condividi<br />Album</button>

 

  
</body>
<script>
	var last_video = "";
	
	function chiudiplayer()
		{
			player.pause();  
			//$("#player").remove();
			$("#player_modal").hide();
			$("#elimina_video").hide();
			$("#condividi_file_singolo").hide();
			$("#condividi_files_multipli").hide();
			$("#chiudi_player").hide();
		}
function delete_video() {


var consenso = confirm("Eliminare PERMANTEMENTE i file selezionati?");
if (consenso)
{
	//console.log(last_video);
	var files = selezionati();
	//console.log(files);
	
  if (files != "")
	{
		 $.ajax({
				  method: "POST",
				  url: "delete.php",
				  data: {fs:files}
				})
				  .done(function( response ) {
						load_replay_btn(4);
						alert(response.message);

				  });	
	}
}
}	


async function onShare() {
	//console.log(last_video);
	var files = selezionati();
	//console.log(files);
	
  const title = document.title;
  if (files != "")
	{
		 $.ajax({
				  method: "POST",
				  url: "shorter.php",
				  data: {files:files}
				})
				  .done(function( response ) {
						var url = "https://yalper.it/sharing.php?fs=" + response;
						console.log("Album url:" + url);
						  var text = "Ti invio un video fatto con YalpeR.it ";
						  try {
							  navigator
							  .share({
								title,
								url,
								text
							  })

								/*
								  Show a message if the user shares something
								*/
								alert(`Thanks for Sharing!`);
							} catch (err) {
							   /*
								  This error will appear if the user cancels the action of sharing.
								*/
							   alert(`Couldn't share ${err}`);
							}


				  });	
	}
  else	
  {	
	var url = last_video;
	
	
	console.log(url);
  var text = "Ti invio un video fatto con YalpeR.it ";
  try {
      await navigator
      .share({
        title,
        url,
        text
      })

        /*
          Show a message if the user shares something
        */
        alert(`Thanks for Sharing!`);
    } catch (err) {
       /*
          This error will appear if the user cancels the action of sharing.
        */
       alert(`Couldn't share ${err}`);
    }
	}
}	

function toggle(div)
{
	if (div == "camera_list")		
		device_list();

	if ($("#" + div).is(":visible"))
		$("#" + div).hide();
	else
		$("#" + div).show();

}
/*
document.addEventListener('keydown', (event) => {
  var keyValue = event.key;
  var codeValue = event.code;
  console.log("keyValue: " + keyValue);
  console.log("codeValue: " + codeValue);
}, false);
*/
/*
   document. addEventListener("backbutton", function(){
		alert("Back");
	   }, false);
	   
	   
window.history.pushState(null, null, window.location.href);
window.onpopstate = function () {
    window.history.go(1);
};

 
   

window.addEventListener('load', function() {
  window.history.pushState({ noBackExitsApp: true }, '')
})

window.addEventListener('popstate', function(event) {
  if (event.state && event.state.noBackExitsApp) {
    window.history.pushState({ noBackExitsApp: true }, '');
    window.history.pushState({ noBackExitsApp: true }, '');
    window.history.pushState({ noBackExitsApp: true }, '');
    window.history.pushState({ noBackExitsApp: true }, '');
    window.history.pushState({ noBackExitsApp: true }, '');
    window.history.pushState({ noBackExitsApp: true }, '');
    window.history.pushState({ noBackExitsApp: true }, '');
    window.history.pushState({ noBackExitsApp: true }, '');
    window.history.pushState({ noBackExitsApp: true }, '');
    window.history.pushState({ noBackExitsApp: true }, '');
    window.history.pushState({ noBackExitsApp: true }, '');
    window.history.pushState({ noBackExitsApp: true }, '');
    window.history.pushState({ noBackExitsApp: true }, '');
    window.history.pushState({ noBackExitsApp: true }, '');
    window.history.pushState({ noBackExitsApp: true }, '');
    window.history.pushState({ noBackExitsApp: true }, '');
    window.history.pushState({ noBackExitsApp: true }, '');
  }
})  
  */

$( document ).ready(function() {
 load_replay_btn(4);
});
function goto_settings()
{
	location.href="settings.php";
}



</script>	
