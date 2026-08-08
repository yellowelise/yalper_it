<?php
session_start();
$iduser = session_id();

if (isset($_SESSION['id']))
	$iduser = $_SESSION['id'];

$event_push = $_REQUEST['event'];
$version = $_REQUEST['version'];


function generateRandomString($length = 10) {
    $characters = '123456789ABCDEFGHJKLMNPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[random_int(0, $charactersLength - 1)];
    }
    return $randomString;
}



if (!isset($_SESSION['logged']))
	header("location: ../login.php");

include('../config/db.php');


if ($stmt = $connection->prepare("SELECT left_credits, used_credits FROM user_credits WHERE user_token = ?")) {
    $stmt->bind_param("s", $_SESSION['token']);
    $stmt->execute();
    $stmt->bind_result($credits_left, $credits_used);
    $stmt->fetch();
    $stmt->close();
} else {
    die("Errore nella query: " . $connection->error);
}



$event_push = generateRandomString();


  require dirname(__DIR__) . '/config/pusher.php';
  $pusher = yalper_create_pusher();


  //$data['message'] = "Anche " . $_SESSION['firstname'] . " sta usando YalpeR - Gaming!";
  //$pusher->trigger('yalper', 'yalper-gaming-presence', $data);
  
	$data['event_push'] = $event_push;
	$pusher->trigger('yalper', 'save-replay-event', $data);
  
  
  
?>
<html lang="it">
  <head>
    <!-- Required meta tags -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
	<link rel="shortcut icon" href="../favicons/favicon.ico">
	  <link rel="icon" type="image/png" sizes="32x32" href="../favicons/favicon32.png">
	  <link rel="icon" type="image/png" sizes="16x16" href="../favicons/favicon16.png">
		<!--link rel="manifest" href="manifest.json?123456" /-->

	  <!-- Apple -->
	  <meta name="apple-mobile-web-app-title" content="YalpeR - Live Replay System">
	<meta name="google-adsense-account" content="ca-pub-9880317414365518">
	  <link rel="apple-touch-icon" sizes="180x180" href="../favicons/favicon180.png">
	<script  src="../js/jquery-3.6.4.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous">


    
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=Titillium+Web&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/styles.css?tagdsok" type="text/css">
	<script src="https://js.pusher.com/8.2.0/pusher.min.js"></script>
    <script src="../public-config.js"></script>
    
</head>
<body>
    <div class="container">
		<button id="startCapture">Inizia Cattura Schermo</button>
		<button class="tags noi" style="position:fixed;width:250px;height:70px;right: 30px; bottom: 60px;display:block;font-size: 1.975rem;" onclick=save_Replay("Gol-noi") >GOAL <span style="font-size:0.5rem;position:absolute;bottom:4px;right:4px;">Noi</span></button>
		<h1>Per collegarti dal cellulare: <?php echo $event_push;?></h1>	
        
        
    </div>
</body>
</html>
<script>


class CircularBuffer {
  constructor(size) {
    this.buffer = new Array(size);
    this.size = size;
    this.head = 0;
    this.tail = 0;
    this.count = 0;  // numero di elementi attualmente nel buffer
  }

  push(element) {
    this.buffer[this.head] = element;
    this.head = (this.head + 1) % this.size;
    
    if (this.count < this.size) {
      this.count++;
    } else {
      this.tail = (this.tail + 1) % this.size; // sposta tail se il buffer è pieno
    }
  }

  // Accesso tramite indice relativo (0 è l'elemento più vecchio)
  get(index) {
    if (index >= this.count) return undefined;
    return this.buffer[(this.tail + index) % this.size];
  }

  // Restituisce l'elemento più vecchio
  peek() {
    if (this.count === 0) return undefined;
    return this.buffer[this.tail];
  }

  // Restituisce tutti gli elementi come array
  toArray() {
    const result = [];
    for (let i = 0; i < this.count; i++) {
      result.push(this.get(i));
    }
    return result;
  }
  // Nuovo metodo O(1)
  isFull() {
    return this.count === this.size;
  }
  
  areAllElementsNonEmpty() {
    if (!this.isFull()) return false;
    
    for (let i = 0; i < this.count; i++) {
      const element = this.get(i);
      if (!element || element.length === 0) {
        return false;
      }
    }
    return true;
  }
  
  // Iterazione sugli elementi
  *[Symbol.iterator]() {
    for (let i = 0; i < this.count; i++) {
      yield this.get(i);
    }
  }
}



let replayTime = 1312; // 1130 * 16 = 18 - 3sec = 15
//let blobBuffer = Array(16).fill().map(() => new Blob()); // Aumentato a 64 segmenti
let bblen = 16;
let blobBuffer = new CircularBuffer(bblen);

let saveBuffer;
let recorder;
let codecs = "video/webm;codecs=vp9";
let vbps = 3900000;



// Nel JavaScript
document.getElementById('startCapture').addEventListener('click', async () => {
  try {
    navigator.mediaDevices.getDisplayMedia({
      video: {
        cursor: "always"
      },
      audio: true
    }).then(mediaStream => {
		        startRecording(mediaStream);

		});
    
    //const videoElement = document.querySelector('video');
    //videoElement.srcObject = stream;


  } catch (error) {
    console.error('Errore:', error);
  }
});



function startRecording(stream) {
    try {
        recorder = new MediaRecorder(stream, {
            type: codecs,
            videoBitsPerSecond: vbps
        });

        recorder.addEventListener("dataavailable", (evt) => {
           // try {
                //blobBuffer.shift();
                //blobBuffer.push(evt.data);
				//blobBuffer = blobBuffer.slice(1);
				//blobBuffer.push(evt.data);                
				blobBuffer.push(evt.data); // 18/12/2024 - new CircularBuffer
				
				
                //show_buffer();
            //} catch (error) {
            //    console.error('Errore gestione buffer:', error);
            //}
        });

        recorder.addEventListener("error", (error) => {
            console.error('Errore MediaRecorder:', error);
            // Tentativo di recupero
            setTimeout(() => {
                if (recorder.state === "inactive") {
                    recordLoop();
                }
            }, 500);
        });

        recordLoop();
    } catch (error) {
        console.error('Errore avvio registrazione:', error);
    }
}




function recordLoop() {
		//	show_buffer();
	
    if (recorder.state === "inactive") {
        recorder.start();
    }

    requestAnimationFrame(() => {
        setTimeout(() => {
            if (recorder.state === "recording") {
                recorder.stop();
                recorder.start();
            }
            recordLoop();
        }, replayTime);
    });
}





function salvaReplay()
{
			let tipologia ="DeskTop-audio";
			saveReplay = false;
			file_totali = bblen;
//			document.getElementById("salva_replay_goal").disabled = true;
			
			let nomeFile = new Date().toLocaleString("it-IT", {timeZone:"Europe/Rome"}).replaceAll(/[:, /]/g, "_");
			let nF = nomeFile;
			
			//saveBuffer = blobBuffer.toArray();
			saveBuffer = blobBuffer.toArray();
			for (let i=0;i<saveBuffer.length;i++)
				{
					setTimeout(upload,(i*20),saveBuffer[i],"part"+i+".webm",nF+tipologia);
				}

			/*for (i=0;i<blobBuffer.length;i++)
				{
					fetch(localStorage.getItem("part"+i+".webm")).then(r => {
						let blob = r.blob();
						console.log(blob);
						
						upload(blob,tipologia + nomeFile.toLocaleString("it-IT",{timeZone:"Europe/Rome"}).replaceAll(":","_").replaceAll(",","_").replaceAll("/","_").replaceAll(" ",""));	
						});
					
				}*/
				 
				   let formData = new FormData();
					//formData.append('minuto', Math.random() * 90);
					formData.append('folder', nF + tipologia);
					//formData.append('partita', event);
					//formData.append('from', from_unique);
            
            
				 fetch("/jobs.php", {
					method: 'POST',
					body: formData
				});

			
			//addVideo(videoURL);
			
		

}




function upload(blob, fileName, folder) {
    return new Promise((resolve, reject) => {
        let serverUrl = '../upload/save.php';
        let formData = new FormData();
        formData.append('video-filename', fileName);
        formData.append('folder', folder);
        formData.append('video-blob', blob);

        fetch(serverUrl, {
            method: 'POST',
            body: formData
        }).then(response => {
            if (!response.ok) {
                throw new Error('Errore nella risposta del server');
            }
           // file_da_caricare--;
            //coda_file();
            resolve();
        }).catch(error => {
            //file_da_caricare--;
            //file_errore++;
            //coda_file();
            reject(error);
        });
    });
}

function save_Replay()
{
		salvaReplay();
}


const publicConfig = window.YALPER_PUBLIC_CONFIG || { pusherKey: '2da066d21277e3a81c67', pusherCluster: 'eu' };
const pusher = new Pusher(publicConfig.pusherKey, {
    cluster: publicConfig.pusherCluster
});

const channel = pusher.subscribe('yalper');
channel.bind('save-replay-event', function(data) {
	//alert("CI SONO!!!");
    save_Replay(); // La tua funzione esistente
});


</script>	
