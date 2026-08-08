<?php
session_start();

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
		<link rel="manifest" href="manifest.json?123" />

	  <!-- Apple -->
	  <meta name="apple-mobile-web-app-title" content="YalpeR - Live Replay System">
	
	  <link rel="apple-touch-icon" sizes="180x180" href="favicons/favicon180.png">
	<script  src="js/jquery-3.6.4.min.js"></script>
			  
    <!-- Bootstrap CSS -->
    <link href="css/bootstrap.min.css" rel="stylesheet">
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=Titillium+Web&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/styles.css?tagsok" type="text/css">
    <script src="js/base64.js"></script>
    <title>YalpeR - Live Replay System</title>
    <style>
      .liveStream {
					position: fixed; right: 0; bottom: 0;
					min-width: 100%; min-height: 100%;
					width: auto; height: auto; z-index: -100;
				}
	.vertical-scrollable {
            position: absolute;
            top: 40px;
            bottom: 40px;
            left: 10px;
            width: 45%;
            overflow-y: scroll;
        }				
	.a_destra {
            position: absolute;
            top: 40px;
            bottom: 40px;
            right: 10px;
            width: 45%;
        }	
        
header {
    position: relative;
    height: 100vh;
    z-index: 0;
}

header video {
    -webkit-transform: translateX(-50%) translateY(-50%);
    -moz-transform: translateX(-50%) translateY(-50%);
    -ms-transform: translateX(-50%) translateY(-50%);
    -o-transform: translateX(-50%) translateY(-50%);
    transform: translateX(-50%) translateY(-50%);
    position: absolute;
    top: 50%;
    left: 50%;
    min-width: 100%;
    min-height: 100%;
    width: auto;
    height: auto;
    z-index: -100;
}      
video {
	background: url('favicons/512.png') 50% 50% / contain no-repeat ;	
  object-fit: fill;
}  			
    </style>
</head>

<body style="overflow:hidden;overscroll-behavior: contain;">
	<header style="overscroll-behavior: contain;">  
	<div style="position:relative;">	
		<video autoplay muted playsinline class="liveStream" id="main_video"></video>
	</div>	
	<div class="row">
		<div class="col-3">
		</div>	
		<div class="col-3" id="crediti_residui" style="color:#fff;text-shadow: -1px 0 black, 0 1px black, 1px 0 black, 0 -1px black;">
			Crediti: <?php echo $credits_left;?>
		</div>	
		<div class="col-5" id="wrapper_camera_list">
			<a href="javascript:;" class="btn btn-lg btn-warning" onclick=relo() >Richiedi Autorizzazioni</a>
			<br />
			<div id="camera_list" style="display:block;">
			</div>
				
		</div>
		<div class="col-1">
		</div>	
	
	</div>	
	<div class="row">
		<div id="message_source" style="display:none;color:#fff;text-shadow: -1px 0 black, 0 1px black, 1px 0 black, 0 -1px black;width:100%;text-align:center;">
		</div>
		<a id="tay" class='btn btn-lg btn-success' style='width:100%;font-size:24px;padding:8px;margin-bottom:3px;text-align:center;display:none;' href='javascript:;' onclick=goto_yalper() >Torna a Yalper</a>
	</div>	

     </header>
</body>
<script>
	

function device_list()
{
$("#camera_list").html("");

var q = 0;
	
navigator.mediaDevices
    .enumerateDevices()
    .then((devices) => {
      devices.forEach((device) => {


	if (`${device.label}` != "")
	{	
		  if (`${device.kind}` == "videoinput")
			{
				//$("#camera_list").append("<a class='btn btn-xs btn-info' style='font-size:10px;padding:2px;' href='javascript:;' onclick=select_camera('XXXX')>Finto</a>");
				
				if (camera_deviceID == `${device.deviceId}`)
					$("#camera_list").append("<a class='btn btn-xs btn-info' style='width:100%;font-size:14px;padding:2px;margin-bottom:3px;text-align:left;' href='javascript:;' onclick=select_camera('"+`${device.deviceId}`+"')><b>>>"+`${device.label}`+"<<</b></a><br />");
				else
					$("#camera_list").append("<a class='btn btn-xs btn-info' style='width:100%;font-size:12px;padding:2px;margin-bottom:3px;text-align:right;' href='javascript:;' onclick=select_camera('"+`${device.deviceId}`+"')>"+`${device.label}`+"</a><br />");
				camera_ldID = `${device.deviceId}`;
				//console.log("curr camera ldID: " + camera_ldID);
				//console.log(`${device.kind}: ${device.label} id = ${device.deviceId}`);
			}
		else
			{
				if (audio_deviceID == `${device.deviceId}`)
					$("#camera_list").append("<a class='btn btn-xs btn-danger' style='width:100%;font-size:14px;padding:2px;margin-bottom:3px;text-align:left;' href='javascript:;' onclick=select_audio('"+`${device.deviceId}`+"')><b>>>"+`${device.label}`+"<<</b></a><br />");
				else
					$("#camera_list").append("<a class='btn btn-xs btn-danger' style='width:100%;font-size:12px;padding:2px;margin-bottom:3px;text-align:right;' href='javascript:;' onclick=select_audio('"+`${device.deviceId}`+"')>"+`${device.label}`+"</a><br />");
				audio_ldID = `${device.deviceId}`;
				//console.log("curr audio ldID: " + audio_ldID);
			}
	}


      });
    })
    .catch((err) => {
      console.error(`${err.name}: ${err.message}`);
    });
}
function select_camera(dID)
{
	camera_deviceID = dID;
	localStorage.setItem("cameraID",camera_deviceID);
	console.log(camera_deviceID);
	device_list();
	if ((camera_deviceID != null) && (audio_deviceID != null))
		setTimeout(boot_up,600);
}

function select_audio(dID)
{
	audio_deviceID = dID;
	localStorage.setItem("audioID",audio_deviceID);
	console.log(audio_deviceID);
	device_list();
	if ((camera_deviceID != null) && (audio_deviceID != null))
		setTimeout(boot_up,600);
}

function toggle(div)
{
	if ($("#" + div).is(":visible"))
		$("#" + div).hide();
	else
		$("#" + div).show();
		
}



function boot_up()
{
console.log("boot_up camera device id: " + camera_deviceID);	
console.log("boot_up audio device id: " + audio_deviceID);	

if (localStream != null)
	localStream.getVideoTracks()[0].stop();

if ((camera_deviceID != null) && (audio_deviceID != null))
	{
		$("#message_source").html("");
		$("#message_source").hide();
	}
else
	{
		$("#message_source").html("<h4>Concedi le autorizzazioni per l'accesso a Video e Microfono ed in seguito seleziona un ingresso Audio (tasto Rosso) ed un ingresso Video (Tasto Blu)</h4>");
		$("#message_source").show();
	}
			

navigator.mediaDevices.getUserMedia(
{
	video: {
			deviceId: camera_deviceID,
			width: { min: 1024, ideal: 1280, max: 1920 },
			height: { min: 576, ideal: 720, max: 1080 },
			type: 'video/webm; codecs="vp9, opus"',

    facingMode: 'environment',
	zoom: true,
	},
    audio: {
			deviceId: audio_deviceID
			}
})
.then(mediaStream => {
		localStream = mediaStream;

	if ((camera_deviceID != null) && (audio_deviceID != null))
	{
		document.getElementById('main_video').srcObject = mediaStream;
		$("#tay").show();
	}
  device_list();
})
.catch(error => {
		console.log('Argh!', error.name || error);
		//startRecording(document.getElementById('main_video').srcObject);
		document.getElementById('main_video').srcObject = null;
	});


}


function goto_yalper()
{
	location.href="capture.html";
}


var camera_deviceID;
var audio_deviceID;
var team_name;
var w,h; 
var localStream;
       
$( document ).ready(function() {
	localStorage.clear();
 
	w = screen.width;
	h = screen.height;

	camera_deviceID = 	localStorage.getItem("cameraID");
	audio_deviceID = 	localStorage.getItem("audioID");
	team_name = localStorage.getItem("teamName");
	$("#message_source").html("<h4>Concedi le autorizzazioni per l'accesso a Video e Microfono ed in seguito seleziona un ingresso Audio (tasto Rosso) ed un ingresso Video (Tasto Blu)</h4>");
	$("#message_source").show();
	console.log("Camera >> Audio: " + camera_deviceID + " >> " +audio_deviceID + " tn:" + team_name);
	console.log("resolution: " + w + "x" + h);   

	setTimeout(function(){
		boot_up();
	},700);


});

function relo()
{
	location.reload(); 
}
</script>	
