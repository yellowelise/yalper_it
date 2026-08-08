<?php
session_start();
$iduser = session_id();
/*
foreach ($_SESSION as $k=>$v) {
    echo "<pre>". $k . ": " . print_r($v,true) . "</pre>";
}
*/


if (isset($_SESSION['id']))
	$iduser = $_SESSION['id'];

    include('config/db.php');
    require_once('config/media.php');

date_default_timezone_set('Europe/Rome');
/*if (!isset($_SESSION['logged']))
	header("location: login.php");
	*/
	
	
$no_go = true;	
$f = "";
$fs_arr = [];
$visibility = 0;
$vid = 0;
if (isset($_GET['f']))
{
	$singlePath = base64_decode((string) $_GET['f'], true);
	$singlePath = $singlePath === false ? null : media_validate_video_relative_path($singlePath);
	if ($singlePath !== null)
	{
		$fs_arr[] = $singlePath;
		$no_go = false;
	}
}


$fs = "";
if (isset($_GET['fs']))	
{
	$fs=(string) $_GET['fs'];
	$fs_def = $fs;

	if (!preg_match('/^[A-Za-z0-9]{30}$/', $fs)) {
		$fs = '';
	}

	$stmt = mysqli_prepare($connection, 'SELECT * FROM url_shorter WHERE shorter = ? LIMIT 1');
	mysqli_stmt_bind_param($stmt, 's', $fs);
	mysqli_stmt_execute($stmt);
	$sqlQuery = mysqli_stmt_get_result($stmt);
	$countRow = mysqli_num_rows($sqlQuery);
        if($countRow > 0){
	            while($rowData = mysqli_fetch_array($sqlQuery)){
					$longLink = rtrim($rowData['long_link'],"|");
					$visibility = rtrim($rowData['visibility']);
					if (($rowData['iduser'] == $iduser) || ($rowData['visibility'] >=3))
						{
							$no_go = false;
							$shareId = (int) $rowData['id'];
							$increment = mysqli_prepare($connection, 'UPDATE url_shorter SET click = click + 1 WHERE id = ?');
							mysqli_stmt_bind_param($increment, 'i', $shareId);
							mysqli_stmt_execute($increment);
							mysqli_stmt_close($increment);
						}

					$fs_arr = explode("|",$longLink);
					for ($i=0;$i<count($fs_arr);$i++)
						{
							$decoded = base64_decode($fs_arr[$i], true);
							$fs_arr[$i] = $decoded === false ? null : media_validate_video_relative_path($decoded, $rowData['user_token']);
						}
					$fs_arr = array_values(array_filter($fs_arr));
					asort($fs_arr);
				}
		}
	mysqli_stmt_close($stmt);

}


if ($no_go)
	{
		header("location: ./feed/");
		exit;
	}

//echo "<pre>visi: " . $visibility . "</pre>";

function q_commenti($conn,$link)
{
	$stmt = mysqli_prepare($conn, 'SELECT COUNT(*) FROM video_comments WHERE video_link = ?');
	mysqli_stmt_bind_param($stmt, 's', $link);
	mysqli_stmt_execute($stmt);
	mysqli_stmt_bind_result($stmt, $count);
	mysqli_stmt_fetch($stmt);
	mysqli_stmt_close($stmt);
	return (int) $count;

}

// Start processing videos to find the first thumbnail and title
$videos = [];
if (is_array($fs_arr))	
{
    foreach ($fs_arr as $k)
    {
        $vid++;
        $name = basename($k);
        $n_arr = explode("_",$name);
        // ... (logic to create $knew)
        $pp = strrpos($k,"/");
        $pre = substr($k,0,$pp);
        $post = substr($k,$pp);
        $knew = $pre . "" . $post;
        $videos[] = str_replace(".mp4",".m3u8",$knew);

    }
}

$firstVideoThumbnail = null;
$firstVideoTitle = null;

if (!empty($videos)) {
    foreach ($videos as $video) {
        $thumbnail = str_replace(".m3u8", "_wall.jpg", $video);
        if (file_exists($thumbnail)) {
            $firstVideoThumbnail = $thumbnail;

            $title = str_replace("-"," ",str_replace(".m3u8", "", basename($video)));
            $title = preg_replace('/[0-9]/', '', $title);
            $title = str_replace("_","", $title);
            $title = str_replace("output", "", $title);

            $arr_title = explode("_",basename( str_replace(".m3u8", "",$video)));
            $arr_title = array_filter($arr_title, function($value) { 
                return ($value !== null && $value !== ''); 
            });
            $arr_title = array_values($arr_title);

            $data = $arr_title[0] . "/" . $arr_title[1] . "/" .  $arr_title[2];
            $ora = $arr_title[3] . ":" . $arr_title[4] . ":" . preg_replace('/[^0-9]/', '',  $arr_title[5]);
            
            $firstVideoTitle = $title . " " . $data . " " . $ora;
            break; // Found the first one, no need to continue
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Yalper Video Player</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
<?php
// Construct the full URL for og:url
$current_page_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";

if ($firstVideoThumbnail) {
    // Assuming thumbnails are in the same directory or a relative path that works.
    // For social media, often a full URL is preferred. Adjust if necessary.
    $full_thumbnail_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]/" . $firstVideoThumbnail;
    echo '    <meta property="og:image" content="' . htmlspecialchars($full_thumbnail_url) . '" />' . "\n";
}
if ($firstVideoTitle) {
    echo '    <meta property="og:title" content="' . htmlspecialchars($firstVideoTitle) . '" />' . "\n";
    echo '    <meta property="og:description" content="Guarda il video ' . htmlspecialchars($firstVideoTitle) . ' su Yalper!" />' . "\n";
}
echo '    <meta property="og:url" content="' . htmlspecialchars($current_page_url) . '" />' . "\n";
echo '    <meta property="og:type" content="video.other" />' . "\n";
?>
    <script src="https://cdn.jsdelivr.net/npm/hls.js@latest"></script>

	
    <style>
        body, html {
            margin: 0;
            padding: 0;
            height: 100%;
            width: 100%;
            overflow: hidden;
        }
     /* Modifica lo stile esistente */
.main-container {
    display: flex;
    height: 100%;
    width: 100%;
    gap: 0px;
    padding: 0px;
    box-sizing: border-box;
    flex-wrap: wrap;
}





        .video-container {
            flex: 4;
            display: flex;
            flex-direction: column;
            height: 100%;
        }
        video {
            width: 100%;
            height: 100%;
            background: #000;
            object-fit: contain;
        }
        .playlist-container {
            flex: 1;
            height: 100%;
            overflow-y: auto;
            background: #f5f5f5;
            padding: 10px;
            box-sizing: border-box;
            min-width: 200px;
            max-width: 300px;
        }
        .playlist-item {
			position:relative;
            margin-bottom: 15px;
            cursor: pointer;
            border-radius: 5px;
            background: white;
            padding: 5px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .playlist-item:hover {
            background: #f0f0f0;
        }
        .playlist-item img {
            width: 100%;
            height: auto;
            border-radius: 5px;
            display: block;
        }
        .playlist-item .title {
            margin-top: 5px;
            font-size: 14px;
            color: #333;
            padding: 5px;
            word-wrap: break-word;
        }
        .playlist-item.active {
            background: #e0e0e0;
            border-left: 4px solid #4CAF50;
        }
        
.reaction-container {
	position:relative;
	
    padding: 6px;
    display: flex;
    align-items: center;
    bottom:5px;
    gap: 10px;
    background: #f1f1f1;
    flex-wrap: wrap;
}

.view-count {
    color: #666;
    font-size: 14px;
}

.reaction-buttons {
    display: flex;
    gap: 10px;
}

.reaction-btn {
    padding: 6px 12px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 5px;
    background: #fff;
    border: 1px solid #ddd;
    font-size: 12px;
}

.reaction-btn.active {
    background: #4CAF50;
    color: white;
}

        a:link { 
  text-decoration: none; 
} 

/* Aggiungi media query per i dispositivi mobili */
@media (max-width: 768px) {
    .main-container {
        flex-direction: column;
    }
    
    .video-container {
        flex: 1;
        min-height: 70vh; /* Aumenta l'altezza del video */
        width: 100%;
    }
    
    .playlist-container {
        flex: 1;
        height: auto; /* Altezza automatica per la playlist */
        max-height: 30vh; /* Riduci l'altezza massima per le miniature */
        width: 100%;
        max-width: 100%;
        display: grid; /* Cambiato a grid */
        grid-template-columns: repeat(3, 1fr); /* 3 colonne uguali */
        gap: 10px; /* Spazio tra gli elementi della griglia */
        overflow-x: hidden; /* Nascondi overflow orizzontale */
        overflow-y: auto; /* Abilita scroll verticale */
        padding: 10px;
    }
    
    .playlist-item {
            margin-bottom: 0; /* Margin handled by grid gap */
            margin-right: 0;
    }

    .comments-container {
        width: 85vw; /* Aumenta la larghezza per una migliore leggibilità */
        transform: translateX(-85vw);
    }
}




.filter-card {
    background: white;
    padding: 15px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    margin-bottom: 15px;
    display: block;
    gap: 10px;
}

.filter-group {
    display: inline-block;
    align-items: center;
    gap: 5px;
}

.filter-group select {
    padding: 8px;
    border-radius: 4px;
    border: 1px solid #ddd;
    font-size: 14px;
}

.comments-container {
    position: absolute;
    left: 0;
    top: 0;
    width: 50vw;
    height: calc(100% - 60px); /* sottrai l'altezza della barra delle reazioni */
    background: rgba(0, 0, 0, 0.7);
    color: white;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    transition: transform 0.3s ease;
    transform: translateX(-50vw); /* nascosto di default */
    z-index: 9999; /* Ensure it's on top */
}

.comments-container-old:hover {
    transform: translateX(0);
}

.comments-toggle {
    position: absolute;
    left: 0px;
    top: 50%;
    transform: translateY(-50%);
    background: rgba(0, 0, 0, 0.7);
    color: white;
    border: none;
    padding: 4px;
    cursor: pointer;
    writing-mode: vertical-rl;
    text-orientation: mixed;
}

.comments-header {
    padding: 5px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.2);
}

.comments-list {
    flex: 1;
    overflow-y: auto;
    padding: 5px;
}

.comment {
    background: rgba(255, 255, 255, 0.1);
    padding:5px;
    border-radius: 4px;
    margin-bottom: 5px;
}

.comment-header {
    display: flex;
    justify-content: space-between;
    font-size: 0.8em;
    opacity: 0.8;
    margin-bottom: 5px;
}

.comment-form {
    padding: 5px;
    border-top: 1px solid rgba(255, 255, 255, 0.2);
}

.comment-form textarea {
    width: 90%;
    background: rgba(255, 255, 255, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.2);
    color: white;
    padding: 4px;
    border-radius: 4px;
    resize: vertical;
    min-height: 60px;
    margin-bottom: 4px;
}

.comment-form button {
    width: 100%;
    padding: 4px;
    background: rgba(255, 255, 255, 0.6);
    color: black;
    border: none;
    border-radius: 4px;
    cursor: pointer;
}

.comment-form button:hover {
    background: rgba(255, 255, 255, 0.9);
}

/* Stile della scrollbar */
.comments-list::-webkit-scrollbar {
    width: 6px;
}

.comments-list::-webkit-scrollbar-track {
    background: rgba(255, 255, 255, 0.1);
}

.comments-list::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.3);
    border-radius: 3px;
}

.comments-container.visible {
    transform: translateX(0);
}

.close-comments-btn {
    background: none;
    border: none;
    color: white;
    font-size: 20px;
    cursor: pointer;
    padding: 5px 10px;
    border-radius: 4px;
}

.close-comments-btn:hover {
    background: rgba(255, 255, 255, 0.1);
}

.button_badge {
  background-color: #fa3e3e;
  border-radius: 2px;
  color: white;
 
  padding: 3px;
  font-size: 18px;
  position:absolute;
  top:5px;
  left:5px;
 
}
    </style>
    
    	<script  src="js/jquery-3.6.4.min.js"></script>

</head>
<body>
    <div class="main-container">
		
		
		
<div class="video-container">
    <video id="video" controls></video>
<button id="show-comments" class="comments-toggle">Commenti</button>

<div class="comments-container">
    <div class="comments-header">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <h3>Commenti</h3>
            <button id="close-comments" class="close-comments-btn">✕</button>
        </div>
    </div>
        <div class="comments-list" id="comments-list">
            <!-- I commenti verranno inseriti qui dinamicamente -->
        </div>
        <div class="comment-form">
            <textarea id="comment-text" placeholder="Scrivi un commento..."></textarea>
            <button id="submit-comment">Invia</button>
        </div>
    </div>
    <div class="reaction-container">
				<div class="view-count">
					<span id="view-counter">0</span> Visualizzazioni
				</div>
				<div class="view-count">
					<a href="javascript:;" id="download" class="reaction-btn">Download</a>
				</div>
				<div class="reaction-buttons">
					<button id="like-btn" class="reaction-btn">
						👍 <span id="like-count">0</span>
					</button>
					<button id="dislike-btn" class="reaction-btn">
						👎 <span id="dislike-count">0</span>
					</button>
				</div>
				<div class="view-title">
					<span id="titolo_video"></span>
				</div>

    </div>
</div>

		

        <div class="playlist-container" id="playlist">

			<div class="filter-card">
				<div class="filter-group">
					<label for="filter-who">Chi:</label>
					<select id="filter-who" style="width:100%">
						<option value="all">Tutti</option>
						<!--option value="noi">Noi</option>
						<option value="avversari">Avversari</option-->
					</select>
				</div>
				<div class="filter-group">
					<label for="filter-type">Tipologia:</label>
					<select id="filter-type" style="width:100%">
						<option value="all">Tutte</option>
						<option value="giocata">Giocata</option>
						<option value="occasione">Occasione</option>
						<option value="gol">Gol</option>
						<option value="inattiva">Palla Inattiva</option>
						<option value="Cartellino">Cartellino</option>
						<option value="sostituzione">Sostituzione</option>
						<option value="parata">Parata</option>
					</select>
				</div>
			</div>


            <?php
					$videoUrls = [];
					foreach ($videos as $video) {
						$videoUrls[] = $video;
						$thumbnail = str_replace(".m3u8", "_wall.jpg", $video);
						$title = str_replace("-"," ",str_replace(".m3u8", "", basename($video)));
						$title = preg_replace('/[0-9]/', '', $title);
						$title = str_replace("_","", $title);
						$title = str_replace("output", "", $title);

						$q_commenti = q_commenti($connection, $video);

						$arr_title = explode("_",basename( str_replace(".m3u8", "",$video)));
						$arr_title = array_filter($arr_title, function($value) { 
							return ($value !== null && $value !== ''); 
						});
						$arr_title = array_values($arr_title);

						$data = $arr_title[0] . "/" . $arr_title[1] . "/" .  $arr_title[2];
						$ora = $arr_title[3] . ":" . $arr_title[4] . ":" . preg_replace('/[^0-9]/', '',  $arr_title[5]);

						echo "<div class='playlist-item' data-video='{$video}' data-titolo='{$title} {$data} {$ora}'>";
						if (file_exists($thumbnail)) {
							echo "<img src='{$thumbnail}' alt='{$title}'>";
						}
						echo "<div class='title'>{$title} {$data} {$ora} <span class='button_badge'>💬&nbsp;{$q_commenti}</span></div>";
						echo "</div>";
					}
            ?>
        </div>
    </div>

    <script>
        const videoUrls = <?php echo json_encode($videoUrls); ?>;
        const video = document.getElementById('video');
        const playlist = document.getElementById('playlist');
        let currentVideoIndex = 0;
        let hls = null;


function findNextVisibleIndex(currentIndex, direction = 1) {
    let index = currentIndex;
    const playlistItems = document.querySelectorAll('.playlist-item');
    
    do {
        index += direction;
        if (index >= playlistItems.length) index = 0;
        if (index < 0) index = playlistItems.length - 1;
        
        // Check if we've gone through all videos
        if (index === currentIndex) return -1;
        
        // Check if the video at this index is visible
        if (playlistItems[index].style.display !== 'none') {
            return index;
        }
    } while (true);
}




function loadVideo(index) {
    const playlistItems = document.querySelectorAll('.playlist-item');
    
    // If the requested video is hidden, find the next visible one
    if (playlistItems[index].style.display === 'none') {
        const nextVisibleIndex = findNextVisibleIndex(index);
        if (nextVisibleIndex === -1) {
            console.log('No visible videos available');
            return;
        }
        index = nextVisibleIndex;
    }

    if (hls) {
        hls.destroy();
    }
            // Rimuovi la classe active da tutti gli elementi
            document.querySelectorAll('.playlist-item').forEach(item => {
                item.classList.remove('active');
            });

            // Aggiungi la classe active all'elemento corrente
            document.querySelector(`[data-video='${videoUrls[index]}']`).classList.add('active');

			const videoTitolo = document.querySelector(`[data-video='${videoUrls[index]}']`).getAttribute('data-titolo');
			document.getElementById('titolo_video').textContent = videoTitolo;

            if (Hls.isSupported()) {
                hls = new Hls({
                    debug: false,
                    enableWorker: true
                });
                
                hls.on(Hls.Events.ERROR, function(event, data) {
                    if (data.fatal) {
                        console.error('HLS error:', data);
                        switch(data.type) {
                            case Hls.ErrorTypes.NETWORK_ERROR:
                                console.log("Network error");
                                hls.startLoad();
                                break;
                            case Hls.ErrorTypes.MEDIA_ERROR:
                                console.log("Media error");
                                hls.recoverMediaError();
                                break;
                            default:
                                hls.destroy();
                                break;
                        }
                    }
                });

                hls.loadSource(videoUrls[index]);
                hls.attachMedia(video);
                console.log(videoUrls[index]);
			
			
						
			  // Get or set iduser in localStorage
				let storedId = localStorage.getItem('iduser');
				if (!storedId) {
					storedId = '<?php echo $iduser; ?>';
					localStorage.setItem('iduser', storedId);
				}
				
				
    			
				var link = videoUrls[index];
				var iduser = storedId;
				var action = '0';
				
				fetch('API/reaction.php', {
				   method: 'POST',
				   headers: {
					   'Content-Type': 'application/x-www-form-urlencoded'
				   },
				   body: `link=${encodeURIComponent(link)}&iduser=${iduser}&action=${action}`
				})
				.then(response => response.json())
				.then(data => {
				   console.log(data);
				})
				.catch(error => {
				   console.error('Error:', error);
				});



                hls.on(Hls.Events.MANIFEST_PARSED, function() {
                    video.play().catch(function(error) {
                        console.log("Play promise failed:", error);
                    });
                });
            } else if (video.canPlayType('application/vnd.apple.mpegurl')) {
                video.src = videoUrls[index];
                video.addEventListener('loadedmetadata', function() {
                    video.play().catch(function(error) {
                        console.log("Play promise failed:", error);
                    });
                });
            }
            currentVideoIndex = index;
            
                const storedId = localStorage.getItem('iduser');
                updateReactions(videoUrls[index], storedId);
        }

        // Aggiungi eventi click agli elementi della playlist
        document.querySelectorAll('.playlist-item').forEach((item, index) => {
            item.addEventListener('click', () => loadVideo(index));
        });

        // Carica automaticamente il prossimo video quando quello corrente finisce
		video.addEventListener('ended', function() {
			const nextVisibleIndex = findNextVisibleIndex(currentVideoIndex);
			if (nextVisibleIndex !== -1) {
				loadVideo(nextVisibleIndex);
			}
		});
        // Carica il primo video all'avvio se esiste almeno un video
        if (videoUrls.length > 0) {
            loadVideo(0);
        }
        
        
        
        
        
function updateReactions(link, iduser) {
    fetch(`API/get_reaction.php?link=${encodeURIComponent(link)}&iduser=${iduser}`)
        .then(response => response.json())
        .then(data => {
            document.getElementById('view-counter').textContent = data.view;
            document.getElementById('like-count').textContent = data.liked;
            document.getElementById('dislike-count').textContent = data.disliked;
            //document.getElementById('titolo_video').textContent = data.title;
            
            // Update button states
            document.getElementById('like-btn').classList.toggle('active', data.user_reaction === '1');
            document.getElementById('dislike-btn').classList.toggle('active', data.user_reaction === '2');
        })
        .catch(error => console.error('Error:', error));
}

function sendReaction(action) {
    const link = videoUrls[currentVideoIndex];
    const iduser = localStorage.getItem('iduser');

    fetch('API/reaction.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: `link=${encodeURIComponent(link)}&iduser=${iduser}&action=${action}`
    })
    .then(response => response.json())
    .then(data => {
        updateReactions(link, iduser);
    })
    .catch(error => console.error('Error:', error));
}




function basename(path) {
   return path.split('/').reverse()[0];
}



function download() {
	if (document.getElementById('download').innerHTML != "Attendere...")
	{
	document.getElementById('download').disabled = true;
	document.getElementById('download').innerHTML  = "Attendere...";
	
    const link = videoUrls[currentVideoIndex];
    fetch('download.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: 'fn=' + encodeURIComponent(link)
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        return response.blob();
    })
    .then(blob => {
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        var fname = basename(link);
        a.download = fname.replace('.m3u8', '.mp4');
        document.body.appendChild(a);
        a.click();
        window.URL.revokeObjectURL(url);
        a.remove();
		document.getElementById('download').disabled = false;
		document.getElementById('download').innerHTML  = "Download";

    })
    .catch(error => {
        console.error('Error:', error);
        alert('Errore durante il download: ' + error.message);
    });
	}
	
}


function applyFilters() {
    //const whoFilter = document.getElementById('filter-who').value;
    const whoFilter = $('#filter-who option:selected').val();
    //console.log(whoFilter);
    const typeFilter = $('#filter-type option:selected').val();
    console.log(typeFilter);

    let anyVisible = false;
	if (typeFilter == "all")
		{
			$(".playlist-item").each(function() {
				$(this).show();
				
				anyVisible = true;	
			});
		}
else
{
    $(".playlist-item").each(function() {
		
		if ($(this).text().toLowerCase().indexOf(typeFilter) > -1) {
			$(this).show();
		} else {
			$(this).hide();
		}    
    
    });
}
    // If no videos are visible, you might want to show a message or clear the player
    if (!anyVisible) {
        if (hls) {
            hls.destroy();
        }
        video.src = '';
        document.getElementById('titolo_video').textContent = 'Nessun video disponibile con i filtri selezionati';
    }
}

// Add event listeners to filters
document.getElementById('filter-who').addEventListener('change', applyFilters);
document.getElementById('filter-type').addEventListener('change', applyFilters);

// Add click handlers for like/dislike buttons
document.getElementById('like-btn').addEventListener('click', () => sendReaction('1'));
document.getElementById('dislike-btn').addEventListener('click', () => sendReaction('2'));
document.getElementById('download').addEventListener('click', () => download());


document.getElementById('submit-comment').addEventListener('click', () => {
    const commentText = document.getElementById('comment-text').value;
    const link = videoUrls[currentVideoIndex];
    
    fetch('API/comments.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            link: link,
            comment: commentText
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.getElementById('comment-text').value = '';
            loadComments();
        }
    });
});

// Carica i commenti quando cambia il video
video.addEventListener('loadedmetadata', loadComments);
        
// Get references to needed elements
const commentsContainer = document.querySelector('.comments-container');
//const commentsToggle = document.querySelector('.comments-toggle');

// Function to handle comments visibility
function toggleComments() {
    const isVisible = commentsContainer.classList.contains('visible');
    commentsContainer.classList.toggle('visible');
    
    if (!isVisible) {
		document.getElementById('show-comments').style.display = 'block';
        // Comments becoming visible - pause video
        video.pause();
    } else {
        // Comments being hidden - resume video
		document.getElementById('show-comments').style.display = 'none';
        video.play();
    }
}

// Add mouseover/mouseout events for the comments container
commentsContainer.addEventListener('mouseenter', () => {
    commentsContainer.classList.add('visible');
	document.getElementById('show-comments').style.display = 'none';
    
    video.pause();
});

commentsContainer.addEventListener('mouseleave', () => {
    commentsContainer.classList.remove('visible');
	document.getElementById('show-comments').style.display = 'block';
    
    video.play();
});


document.getElementById('close-comments').addEventListener('click', () => {
    commentsContainer.classList.remove('visible');
	document.getElementById('show-comments').style.display = 'block';

    video.play();
});

document.getElementById('show-comments').addEventListener('click', () => {
    commentsContainer.classList.add('visible');
	document.getElementById('show-comments').style.display = 'none';
    
    video.play();
});


function escapeCommentHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, character => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    })[character]);
}

// Carica i commenti facendo escaping anche dei record storici già nel DB.
function loadComments() {
    const link = videoUrls[currentVideoIndex];
    fetch(`API/comments.php?link=${encodeURIComponent(link)}`)
        .then(response => response.json())
        .then(comments => {
            const commentsList = document.getElementById('comments-list');
            commentsList.innerHTML = comments.map(comment => `
                <div class="comment">
                    <div class="comment-header">
                        <span class="comment-author">${escapeCommentHtml(comment.username || 'Ospite')}</span>
                        <span class="comment-date">${new Date(comment.created_at).toLocaleString()}</span>
                    </div>
                    <div class="comment-content">${escapeCommentHtml(comment.comment).replace(/\n/g, '<br>')}</div>
                </div>
            `).join('');
        });
}
    </script>
</body>
</html>

