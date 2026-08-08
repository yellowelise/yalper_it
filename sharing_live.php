<?php
session_start();
//error_reporting(E_ALL);
//ini_set('display_errors', 1);

include('config/db.php');
date_default_timezone_set('Europe/Rome');

$video_url = '';
$session_title = 'Sessione Live';
$session_date = '';
$segment_count = 0;
$thumb_url = '';
$error_message = '';
$share_url = '';

if (isset($_REQUEST['code']) && isset($_REQUEST['t'])) {
    $code = base64_decode($_REQUEST['code']);
    $token = base64_decode($_REQUEST['t']);

    if ($code && $token) {
        $prefix = basename($code); // Sanitize to prevent directory traversal
        $token = basename($token);  // Sanitize to prevent directory traversal

        // URL per condivisione
        $share_url = "https://yalper.it/sharing_live.php?code=" . urlencode($_REQUEST['code']) . "&t=" . urlencode($_REQUEST['t']);

        // Estrai data e ora dalla sessione
        if (preg_match('/^(\d{2})_(\d{2})_(\d{4})__(\d{2})_(\d{2})_(\d{2})/', $prefix, $dateMatches)) {
            $session_date = "{$dateMatches[1]}/{$dateMatches[2]}/{$dateMatches[3]}";
            $session_time = "{$dateMatches[4]}:{$dateMatches[5]}";
            $session_title = "{$session_date} ore {$session_time}";
        } else {
            $session_title = str_replace("Live-Seg", "", $prefix);
            $session_title = str_replace("__", " ", $session_title);
            $session_title = str_replace("_", ":", $session_title);
        }

        $user_upload_dir = "upload/uploads/{$token}";
        $glob_pattern = "{$user_upload_dir}/{$prefix}*-output.m3u8";

        $segment_m3u8_files = glob($glob_pattern);
        $segment_count = count($segment_m3u8_files);

        // Trova thumbnail
        $thumbAudio = "{$user_upload_dir}/{$prefix}001-audio-output.jpg";
        $thumbVideo = "{$user_upload_dir}/{$prefix}001-output.jpg";
        if (file_exists($thumbAudio)) {
            $thumb_url = "https://yalper.it/" . $thumbAudio;
        } elseif (file_exists($thumbVideo)) {
            $thumb_url = "https://yalper.it/" . $thumbVideo;
        }

        usort($segment_m3u8_files, function($a, $b) {
            preg_match('/Live-Seg(\d+)/', $a, $matchesA);
            preg_match('/Live-Seg(\d+)/', $b, $matchesB);
            $numA = isset($matchesA[1]) ? (int)$matchesA[1] : 0;
            $numB = isset($matchesB[1]) ? (int)$matchesB[1] : 0;
            return $numA - $numB;
        });

        if (!empty($segment_m3u8_files)) {
            $master_playlist_content = "#EXTM3U\n#EXT-X-VERSION:3\n";
            $ts_segments_content = "";
            $max_target_duration = 0;
            $is_first_segment_file = true;

            foreach ($segment_m3u8_files as $m3u8_file) {
                if (file_exists($m3u8_file)) {

                    if (!$is_first_segment_file) {
                        $ts_segments_content .= "#EXT-X-DISCONTINUITY\n";
                    }

                    $m3u8_content = file_get_contents($m3u8_file);
                    $m3u8_dir_path = dirname($m3u8_file);

                    if (preg_match('/#EXT-X-TARGETDURATION:(\d+)/', $m3u8_content, $matches)) {
                        if ((int)$matches[1] > $max_target_duration) {
                            $max_target_duration = (int)$matches[1];
                        }
                    }

                    $lines = explode("\n", $m3u8_content);
                    foreach ($lines as $line) {
                        $line = trim($line);
                        if (strpos($line, '#EXTINF:') === 0) {
                            $ts_segments_content .= $line . "\n";
                        } elseif (substr($line, -3) === '.ts') {
                            $ts_relative_path = $m3u8_dir_path . '/' . $line;
                            $ts_segments_content .= basename($ts_relative_path) . "\n";
                        }
                    }
                    $is_first_segment_file = false;
                }
            }

            if (!empty($ts_segments_content)) {
                if ($max_target_duration == 0) {
                    $max_target_duration = 10; // Sensible default
                }
                $master_playlist_content .= "#EXT-X-TARGETDURATION:{$max_target_duration}\n";
                $master_playlist_content .= "#EXT-X-MEDIA-SEQUENCE:0\n";
                $master_playlist_content .= $ts_segments_content;
                $master_playlist_content .= "#EXT-X-ENDLIST\n";

                $master_playlist_filename = $user_upload_dir . '/' . $prefix . '_all.m3u8';
                file_put_contents($master_playlist_filename, $master_playlist_content);

                $video_url = $master_playlist_filename;

            } else {
                 $error_message = "Nessun segmento .ts valido trovato per questa sessione.";
            }

        } else {
            $error_message = "Nessuna sessione trovata per il codice fornito. Il link potrebbe essere scaduto o non corretto.";
        }

    } else {
        $error_message = "Parametri di condivisione non validi o corrotti.";
    }
} else {
    $error_message = "Link di condivisione incompleto. Mancano dei parametri.";
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>YalpeR - <?php echo htmlspecialchars($session_title); ?></title>

    <!-- Open Graph per condivisione social -->
    <meta property="og:type" content="video.other">
    <meta property="og:title" content="YalpeR Live - <?php echo htmlspecialchars($session_title); ?>">
    <meta property="og:description" content="Guarda questa sessione live registrata con YalpeR">
    <meta property="og:url" content="<?php echo htmlspecialchars($share_url); ?>">
    <?php if (!empty($thumb_url)): ?>
    <meta property="og:image" content="<?php echo htmlspecialchars($thumb_url); ?>">
    <?php endif; ?>
    <meta property="og:site_name" content="YalpeR">

    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="YalpeR Live - <?php echo htmlspecialchars($session_title); ?>">
    <meta name="twitter:description" content="Guarda questa sessione live registrata con YalpeR">
    <?php if (!empty($thumb_url)): ?>
    <meta name="twitter:image" content="<?php echo htmlspecialchars($thumb_url); ?>">
    <?php endif; ?>

    <link rel="shortcut icon" href="favicons/favicon.ico">
    <link rel="icon" type="image/png" sizes="32x32" href="favicons/favicon32.png">
    <script src="https://cdn.jsdelivr.net/npm/hls.js@latest"></script>
    <link rel="stylesheet" href="css/bootstrap.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body, html {
            height: 100%;
            width: 100%;
            background: linear-gradient(135deg, #0f0f23 0%, #1a1a2e 50%, #16213e 100%);
            color: #fff;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            overflow-x: hidden;
        }
        .main-container {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        /* Header */
        .header {
            padding: 15px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: rgba(0,0,0,0.3);
            backdrop-filter: blur(10px);
            position: sticky;
            top: 0;
            z-index: 100;
        }
        .logo {
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            color: #fff;
        }
        .logo-icon {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #28a745, #20c997);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 18px;
        }
        .logo-text {
            font-size: 1.4rem;
            font-weight: 700;
            background: linear-gradient(135deg, #28a745, #20c997);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .header-actions {
            display: flex;
            gap: 10px;
        }
        .btn-share-header {
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.2);
            color: #fff;
            padding: 8px 16px;
            border-radius: 20px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 14px;
            transition: all 0.3s;
        }
        .btn-share-header:hover {
            background: rgba(255,255,255,0.2);
        }
        /* Video Section */
        .video-section {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 20px;
            padding-bottom: 100px;
        }
        .session-info {
            text-align: center;
            margin-bottom: 20px;
            max-width: 600px;
        }
        .session-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(40, 167, 69, 0.2);
            border: 1px solid rgba(40, 167, 69, 0.4);
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            color: #28a745;
            margin-bottom: 12px;
        }
        .session-title {
            font-size: 1.6rem;
            font-weight: 600;
            margin-bottom: 8px;
        }
        .session-meta {
            color: rgba(255,255,255,0.6);
            font-size: 14px;
        }
        .video-wrapper {
            width: 100%;
            max-width: 1000px;
            position: relative;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0,0,0,0.4);
            background: #000;
        }
        .video-wrapper::before {
            content: '';
            position: absolute;
            top: -2px;
            left: -2px;
            right: -2px;
            bottom: -2px;
            background: linear-gradient(135deg, #28a745, #20c997, #17a2b8);
            border-radius: 18px;
            z-index: -1;
        }
        video {
            width: 100%;
            height: auto;
            max-height: 70vh;
            display: block;
            background: #000;
        }
        /* Share Bar */
        .share-bar {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: rgba(15, 15, 35, 0.95);
            backdrop-filter: blur(20px);
            padding: 15px 20px;
            display: flex;
            justify-content: center;
            gap: 12px;
            border-top: 1px solid rgba(255,255,255,0.1);
        }
        .share-btn {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .share-btn:hover {
            transform: scale(1.1);
            box-shadow: 0 5px 20px rgba(0,0,0,0.3);
        }
        .share-btn:active {
            transform: scale(0.95);
        }
        .share-btn.whatsapp { background: #25D366; }
        .share-btn.telegram { background: #0088cc; }
        .share-btn.facebook { background: #1877F2; }
        .share-btn.twitter { background: #000; }
        .share-btn.copy {
            background: linear-gradient(135deg, #667eea, #764ba2);
        }
        .share-btn svg {
            width: 24px;
            height: 24px;
            fill: white;
        }
        /* Toast */
        .toast-notification {
            position: fixed;
            bottom: 90px;
            left: 50%;
            transform: translateX(-50%) translateY(100px);
            background: #28a745;
            color: #fff;
            padding: 12px 24px;
            border-radius: 25px;
            font-size: 14px;
            font-weight: 500;
            opacity: 0;
            transition: all 0.3s ease;
            z-index: 1000;
        }
        .toast-notification.show {
            transform: translateX(-50%) translateY(0);
            opacity: 1;
        }
        /* Error */
        .error-container {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
        }
        .error-card {
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 20px;
            padding: 40px;
            text-align: center;
            max-width: 400px;
        }
        .error-icon {
            width: 80px;
            height: 80px;
            background: rgba(220, 53, 69, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 36px;
        }
        .error-card h2 {
            font-size: 1.5rem;
            margin-bottom: 10px;
        }
        .error-card p {
            color: rgba(255,255,255,0.6);
            margin-bottom: 25px;
            line-height: 1.6;
        }
        .btn-home {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: linear-gradient(135deg, #28a745, #20c997);
            color: #fff;
            text-decoration: none;
            padding: 12px 28px;
            border-radius: 25px;
            font-weight: 500;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .btn-home:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(40, 167, 69, 0.3);
            color: #fff;
        }
        /* Loading */
        .loading-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.8);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10;
        }
        .spinner {
            width: 50px;
            height: 50px;
            border: 3px solid rgba(255,255,255,0.1);
            border-top-color: #28a745;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        /* Responsive */
        @media (max-width: 600px) {
            .header { padding: 12px 15px; }
            .logo-text { display: none; }
            .session-title { font-size: 1.3rem; }
            .video-section { padding: 15px; padding-bottom: 90px; }
            .share-bar { padding: 12px 15px; gap: 10px; }
            .share-btn { width: 44px; height: 44px; }
            .share-btn svg { width: 20px; height: 20px; }
        }
    </style>
</head>
<body>
    <div class="main-container">
        <!-- Header -->
        <header class="header">
            <a href="https://yalper.it" class="logo">
                <div class="logo-icon">Y</div>
                <span class="logo-text">YalpeR</span>
            </a>
            <?php if (empty($error_message)): ?>
            <div class="header-actions">
                <button class="btn-share-header" onclick="openShareModal()">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M18 16.08c-.76 0-1.44.3-1.96.77L8.91 12.7c.05-.23.09-.46.09-.7s-.04-.47-.09-.7l7.05-4.11c.54.5 1.25.81 2.04.81 1.66 0 3-1.34 3-3s-1.34-3-3-3-3 1.34-3 3c0 .24.04.47.09.7L8.04 9.81C7.5 9.31 6.79 9 6 9c-1.66 0-3 1.34-3 3s1.34 3 3 3c.79 0 1.5-.31 2.04-.81l7.12 4.16c-.05.21-.08.43-.08.65 0 1.61 1.31 2.92 2.92 2.92s2.92-1.31 2.92-2.92-1.31-2.92-2.92-2.92z"/></svg>
                    Condividi
                </button>
            </div>
            <?php endif; ?>
        </header>

        <?php if (!empty($error_message)): ?>
        <!-- Error State -->
        <div class="error-container">
            <div class="error-card">
                <div class="error-icon">⚠️</div>
                <h2>Video non disponibile</h2>
                <p><?php echo htmlspecialchars($error_message); ?></p>
                <a href="https://yalper.it" class="btn-home">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/></svg>
                    Vai a YalpeR
                </a>
            </div>
        </div>
        <?php else: ?>
        <!-- Video Section -->
        <section class="video-section">
            <div class="session-info">
                <div class="session-badge">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor"><path d="M17 10.5V7c0-.55-.45-1-1-1H4c-.55 0-1 .45-1 1v10c0 .55.45 1 1 1h12c.55 0 1-.45 1-1v-3.5l4 4v-11l-4 4z"/></svg>
                    SESSIONE LIVE
                </div>
                <h1 class="session-title"><?php echo htmlspecialchars($session_title); ?></h1>
                <?php if ($segment_count > 0): ?>
                <p class="session-meta"><?php echo $segment_count; ?> segmenti registrati</p>
                <?php endif; ?>
            </div>

            <div class="video-wrapper">
                <div class="loading-overlay" id="loadingOverlay">
                    <div class="spinner"></div>
                </div>
                <video id="video" controls playsinline></video>
            </div>
        </section>

        <!-- Share Bar -->
        <div class="share-bar">
            <button class="share-btn whatsapp" onclick="shareWhatsApp()" title="WhatsApp">
                <svg viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
            </button>
            <button class="share-btn telegram" onclick="shareTelegram()" title="Telegram">
                <svg viewBox="0 0 24 24"><path d="M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.48.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z"/></svg>
            </button>
            <button class="share-btn facebook" onclick="shareFacebook()" title="Facebook">
                <svg viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
            </button>
            <button class="share-btn twitter" onclick="shareTwitter()" title="X">
                <svg viewBox="0 0 24 24"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
            </button>
            <button class="share-btn copy" onclick="copyLink()" title="Copia link">
                <svg viewBox="0 0 24 24"><path d="M16 1H4c-1.1 0-2 .9-2 2v14h2V3h12V1zm3 4H8c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h11c1.1 0 2-.9 2-2V7c0-1.1-.9-2-2-2zm0 16H8V7h11v14z"/></svg>
            </button>
        </div>

        <!-- Toast -->
        <div class="toast-notification" id="toast"></div>

        <script>
            const shareUrl = '<?php echo addslashes($share_url); ?>';
            const shareTitle = 'YalpeR Live - <?php echo addslashes($session_title); ?>';
            const shareText = 'Guarda questa sessione live su YalpeR: <?php echo addslashes($session_title); ?>';

            // Video player
            document.addEventListener('DOMContentLoaded', function() {
                const video = document.getElementById('video');
                const loading = document.getElementById('loadingOverlay');
                const videoSrc = '<?php echo addslashes($video_url); ?>';

                function hideLoading() {
                    if (loading) loading.style.display = 'none';
                }

                if (Hls.isSupported()) {
                    const hls = new Hls();
                    hls.loadSource(videoSrc);
                    hls.attachMedia(video);
                    hls.on(Hls.Events.MANIFEST_PARSED, function() {
                        hideLoading();
                        video.play().catch(e => console.log('Autoplay blocked'));
                    });
                    hls.on(Hls.Events.ERROR, function() {
                        hideLoading();
                    });
                } else if (video.canPlayType('application/vnd.apple.mpegurl')) {
                    video.src = videoSrc;
                    video.addEventListener('loadedmetadata', function() {
                        hideLoading();
                        video.play().catch(e => console.log('Autoplay blocked'));
                    });
                    video.addEventListener('error', hideLoading);
                }

                // Fallback timeout
                setTimeout(hideLoading, 5000);
            });

            // Share functions
            function showToast(msg) {
                const toast = document.getElementById('toast');
                toast.textContent = msg;
                toast.classList.add('show');
                setTimeout(() => toast.classList.remove('show'), 2500);
            }

            function shareWhatsApp() {
                window.open('https://wa.me/?text=' + encodeURIComponent(shareText + ' ' + shareUrl), '_blank');
            }

            function shareTelegram() {
                window.open('https://t.me/share/url?url=' + encodeURIComponent(shareUrl) + '&text=' + encodeURIComponent(shareText), '_blank');
            }

            function shareFacebook() {
                window.open('https://www.facebook.com/sharer/sharer.php?u=' + encodeURIComponent(shareUrl), '_blank');
            }

            function shareTwitter() {
                window.open('https://twitter.com/intent/tweet?text=' + encodeURIComponent(shareText) + '&url=' + encodeURIComponent(shareUrl), '_blank');
            }

            function copyLink() {
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(shareUrl).then(() => {
                        showToast('Link copiato!');
                    }).catch(() => fallbackCopy());
                } else {
                    fallbackCopy();
                }
            }

            function fallbackCopy() {
                const ta = document.createElement('textarea');
                ta.value = shareUrl;
                ta.style.position = 'fixed';
                ta.style.opacity = '0';
                document.body.appendChild(ta);
                ta.select();
                try {
                    document.execCommand('copy');
                    showToast('Link copiato!');
                } catch(e) {
                    showToast('Impossibile copiare');
                }
                document.body.removeChild(ta);
            }

            function openShareModal() {
                if (navigator.share) {
                    navigator.share({
                        title: shareTitle,
                        text: shareText,
                        url: shareUrl
                    }).catch(() => {});
                } else {
                    copyLink();
                }
            }
        </script>
        <?php endif; ?>
    </div>
</body>
</html>