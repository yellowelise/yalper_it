<!DOCTYPE html>
<html>
<head>
    <title>Yalper Video Player</title>
    <script src="https://cdn.jsdelivr.net/npm/hls.js@latest"></script>
    <style>
        body, html {
            margin: 0;
            padding: 0;
            height: 100%;
            width: 100%;
            overflow: hidden;
        }
        .main-container {
            display: flex;
            height: 100vh;
            width: 100%;
            gap: 0px;
            padding: 0px;
            box-sizing: border-box;
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
    </style>
</head>
<body>
    <div class="main-container">
        <div class="video-container">
            <video id="video" controls></video>
        </div>
        <div class="playlist-container" id="playlist">
            <?php
            $videos = glob("*.m3u8");
            $videoUrls = [];
            foreach ($videos as $video) {
                $videoUrls[] = $video;
                $thumbnail = str_replace(".m3u8", "_wall.jpg", str_replace("_IOS","",$video));
                $title = str_replace("-"," ",substr(str_replace(".m3u8", "", $video),20));
                $title = str_replace("output", "", $title);
                $data = str_replace("_","/",substr(str_replace(".m3u8", "", $video),0,10));
                $ora = str_replace("_",":",substr(str_replace(".m3u8", "", $video),12,8));
                echo "<div class='playlist-item' data-video='{$video}'>";
                if (file_exists($thumbnail)) {
                    echo "<img src='{$thumbnail}' alt='{$title}'>";
                }
                echo "<div class='title'>{$title} {$data} {$ora}</div>";
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

        function loadVideo(index) {
            if (hls) {
                hls.destroy();
            }

            // Rimuovi la classe active da tutti gli elementi
            document.querySelectorAll('.playlist-item').forEach(item => {
                item.classList.remove('active');
            });

            // Aggiungi la classe active all'elemento corrente
            document.querySelector(`[data-video='${videoUrls[index]}']`).classList.add('active');

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
        }

        // Aggiungi eventi click agli elementi della playlist
        document.querySelectorAll('.playlist-item').forEach((item, index) => {
            item.addEventListener('click', () => loadVideo(index));
        });

        // Carica automaticamente il prossimo video quando quello corrente finisce
        video.addEventListener('ended', function() {
            if (currentVideoIndex < videoUrls.length - 1) {
                loadVideo(currentVideoIndex + 1);
            }
        });

        // Carica il primo video all'avvio se esiste almeno un video
        if (videoUrls.length > 0) {
            loadVideo(0);
        }
    </script>
</body>
</html>
