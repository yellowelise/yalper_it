<?php
session_start();

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: *");

include('config/db.php');
require_once('config/auth.php');

$div = "6";
if (isset($_REQUEST['div'])) {
    $div = $_REQUEST['div'];
}

$OTT = "";
if (isset($_REQUEST['OTT'])) {
    $OTT = $_REQUEST['OTT'];
}

$token = "";
if (isset($_REQUEST['token'])) {
    $token = $_REQUEST['token'];
}

if (empty($OTT) || empty($token)) {
    die("Manca Autorizzazione!");
}

$authenticatedUser = auth_validate_credentials($connection, $OTT, $token);
if (!$authenticatedUser) {
    http_response_code(401);
    die("Sessione non valida o scaduta.");
}

// Il token usato nei percorsi deve provenire dal DB, non dalla richiesta.
$token = $authenticatedUser['token'];

// Cerca solo le master playlist delle sessioni live
$searchPath = __DIR__ . DIRECTORY_SEPARATOR . "upload" . DIRECTORY_SEPARATOR . "uploads" . DIRECTORY_SEPARATOR . $token . DIRECTORY_SEPARATOR . "*Live-Seg_all.m3u8";
$allMasterFiles = glob($searchPath);

// Ordina i file in ordine cronologico inverso (i più recenti prima)
usort($allMasterFiles, function($a, $b) {
    return filemtime($b) - filemtime($a);
});

// HTML per i controlli
$html = "<div class='row'>
            <div class='col-6'>
                <a style='margin-bottom:2px;width:100%;' class='btn btn-sm btn-success' href='javascript:;' onclick=\"load_live_btn('{$div}')\">Aggiorna</a>
            </div>
            <div class='col-2'>
                <a style='margin-bottom:2px;width:100%;padding-left:3px;' class='btn btn-sm btn-success' href='javascript:;' onclick=\"load_live_btn('12')\">1Col.</a>
            </div>
            <div class='col-2'>
                <a style='margin-bottom:2px;width:100%;padding-left:3px;' class='btn btn-sm btn-success' href='javascript:;' onclick=\"load_live_btn('6')\">2Col.</a>
            </div>
            <div class='col-2'>
                <a style='margin-bottom:2px;width:100%;padding-left:3px;' class='btn btn-sm btn-success' href='javascript:;' onclick=\"load_live_btn('4')\">3Col.</a>
            </div>
        </div>";

$html .= "<div class='row' style='margin-top: 15px;'>";

$inc_id = 0;
if (!empty($allMasterFiles)) {
    foreach ($allMasterFiles as $file) {
        $inc_id++;
        $basename = basename($file);

        // Estrai il titolo della sessione dal nome del file
        // e.g., 04_12_2025__14_30_00Live-Seg_all.m3u8 -> 04/12/2025 14:30:00
        if (preg_match('/^(\d{2})_(\d{2})_(\d{4})__(\d{2})_(\d{2})_(\d{2})/', $basename, $matches)) {
            $sessionTitle = "{$matches[1]}/{$matches[2]}/{$matches[3]} {$matches[4]}:{$matches[5]}:{$matches[6]}";
        } else {
            $sessionTitle = "Sessione Live";
        }
        
        // Codifica del percorso per il javascript
        $encodedPath = base64_encode("upload/uploads/{$token}/" . $basename);

        // Estrai session prefix dal nome file
        // e.g., 04_12_2025__14_30_00Live-Seg_all.m3u8 -> 04_12_2025__14_30_00Live-Seg
        $sessionPrefix = '';
        if (preg_match('/^(.*Live-Seg)_all\.m3u8$/', $basename, $prefixMatches)) {
            $sessionPrefix = $prefixMatches[1];
        }

        // Genera i parametri per la condivisione
        $shareCode = base64_encode($sessionPrefix);
        $shareToken = base64_encode($token);
        $shareUrl = "https://yalper.it/sharing_live.php?code=" . urlencode($shareCode) . "&t=" . urlencode($shareToken);

        // Trova un'immagine di thumbnail (basata sul nome della sessione)
        $thumbUrl = '';
        if (!empty($sessionPrefix)) {
            // Attempt to find thumbnail for the first segment (seg 001)
            $firstSegmentThumbAudio = $sessionPrefix . '001-audio-output.jpg';
            $firstSegmentThumbVideo = $sessionPrefix . '001-output.jpg'; // For video-only segments

            $fullPathThumbAudio = "upload/uploads/{$token}/" . $firstSegmentThumbAudio;
            $fullPathThumbVideo = "upload/uploads/{$token}/" . $firstSegmentThumbVideo;

            if (file_exists($fullPathThumbAudio)) {
                $thumbUrl = "https://yalper.it/" . $fullPathThumbAudio;
            } elseif (file_exists($fullPathThumbVideo)) {
                $thumbUrl = "https://yalper.it/" . $fullPathThumbVideo;
            }
        }
		
		$html .= "<!-- DEBUG INFO:
			Base Name: {$basename}
			Session Prefix: " . ($sessionPrefix ?? 'N/A') . "
			Thumb Audio Path Checked: " . ($fullPathThumbAudio ?? 'N/A') . "
			Thumb Video Path Checked: " . ($fullPathThumbVideo ?? 'N/A') . "
			Thumb URL Found: {$thumbUrl}
		-->";

        // Icona share (Font Awesome style)
        $shareIcon = "<svg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 512 512' fill='#28a745'><path d='M307 34.8c-11.5 5.1-19 16.6-19 29.2v64H176C78.8 128 0 206.8 0 304C0 417.3 81.5 467.9 100.2 478.1c2.5 1.4 5.3 1.9 8.1 1.9c10.9 0 19.7-8.9 19.7-19.7c0-7.5-4.3-14.4-9.8-19.5C108.8 431.9 96 414.4 96 384c0-53 43-96 96-96h96v64c0 12.6 7.4 24.1 19 29.2s25 3 34.4-5.4l160-144c6.7-6.1 10.6-14.7 10.6-23.8s-3.8-17.7-10.6-23.8l-160-144c-9.4-8.5-22.9-10.6-34.4-5.4z'/></svg>";

        if (!empty($thumbUrl)) {
            // Mostra un bottone grande con immagine di sfondo
            $html .= "
            <div class='col-{$div}' style='margin-bottom: 15px;'>
                <div style='position: relative; border-radius: 10px; overflow: hidden; border: 2px solid #28a745;'>
                    <a href='javascript:;' onclick=\"visualizza_live(this, '{$encodedPath}')\" style='display: block; position: relative; text-align: center; text-decoration: none;'>
                        <img src='{$thumbUrl}' style='width: 100%; height: 80px; object-fit: cover; filter: brightness(0.6);' />
                        <div style='position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); color: #fff; font-size: 1.1rem; font-weight: bold; text-shadow: 2px 2px 4px rgba(0,0,0,0.8);'>
                            ▶️ {$sessionTitle}
                        </div>
                    </a>
                    <button onclick=\"openShareModal('{$shareUrl}', '{$sessionTitle}', '{$thumbUrl}')\" style='position: absolute; top: 5px; right: 5px; background: rgba(255,255,255,0.95); border: none; border-radius: 50%; width: 32px; height: 32px; cursor: pointer; display: flex; align-items: center; justify-content: center; box-shadow: 0 2px 6px rgba(0,0,0,0.3);' title='Condividi'>
                        {$shareIcon}
                    </button>
                </div>
            </div>";
        } else {
            // Mostra un semplice bottone se non c'è thumbnail
            $html .= "
            <div class='col-{$div}' style='margin-bottom: 10px;'>
                <div style='position: relative;'>
                    <a style='width:100%; padding: 10px;' class='btn btn-lg btn-success' href='javascript:;' onclick=\"visualizza_live(this, '{$encodedPath}')\">
                        ▶️ {$sessionTitle}
                    </a>
                    <button onclick=\"openShareModal('{$shareUrl}', '{$sessionTitle}', '')\" style='position: absolute; top: 50%; right: 10px; transform: translateY(-50%); background: rgba(255,255,255,0.95); border: none; border-radius: 50%; width: 32px; height: 32px; cursor: pointer; display: flex; align-items: center; justify-content: center; box-shadow: 0 2px 6px rgba(0,0,0,0.3);' title='Condividi'>
                        {$shareIcon}
                    </button>
                </div>
            </div>";
        }
    }
} else {
    $html .= "<div class='col-12'><div class='alert alert-info' style='margin-top:20px;'>Nessuna sessione Live completata trovata.</div></div>";
}

$html .= "</div>";

echo $html;
?>

<script>
function toggleSession(sessionId) {
	var element = document.getElementById(sessionId);
	var arrow = document.getElementById(sessionId + '_arrow');
	if (element.style.display === 'none') {
		element.style.display = 'flex';
		arrow.textContent = '▼';
	} else {
		element.style.display = 'none';
		arrow.textContent = '▶';
	}
}

function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(function() {
        showToast('Link copiato negli appunti!');
    }, function(err) {
        console.error('Errore nel copiare il link: ', err);
        // Fallback per browser che non supportano clipboard API
        var textArea = document.createElement('textarea');
        textArea.value = text;
        document.body.appendChild(textArea);
        textArea.select();
        try {
            document.execCommand('copy');
            showToast('Link copiato negli appunti!');
        } catch (e) {
            showToast('Impossibile copiare il link', true);
        }
        document.body.removeChild(textArea);
    });
}

function showToast(message, isError) {
    var toast = document.getElementById('share-toast');
    if (!toast) {
        toast = document.createElement('div');
        toast.id = 'share-toast';
        toast.style.cssText = 'position:fixed;bottom:100px;left:50%;transform:translateX(-50%);padding:12px 24px;border-radius:25px;color:#fff;font-size:14px;z-index:10001;opacity:0;transition:opacity 0.3s;';
        document.body.appendChild(toast);
    }
    toast.style.background = isError ? '#dc3545' : '#28a745';
    toast.textContent = message;
    toast.style.opacity = '1';
    setTimeout(function() { toast.style.opacity = '0'; }, 2500);
}

function openShareModal(url, title, thumbUrl) {
    // Rimuovi modal esistente se presente
    var existingModal = document.getElementById('share-modal');
    if (existingModal) existingModal.remove();

    var shareText = 'Guarda questa sessione live su YalpeR: ' + title;

    var modal = document.createElement('div');
    modal.id = 'share-modal';
    modal.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.85);display:flex;justify-content:center;align-items:center;z-index:10000;';
    modal.onclick = function(e) { if (e.target === modal) closeShareModal(); };

    var content = document.createElement('div');
    content.style.cssText = 'background:#fff;border-radius:16px;padding:24px;max-width:360px;width:90%;text-align:center;position:relative;';

    // Header con titolo e chiudi
    content.innerHTML = '<button onclick="closeShareModal()" style="position:absolute;top:10px;right:10px;background:none;border:none;font-size:24px;cursor:pointer;color:#666;">×</button>' +
        '<h3 style="margin:0 0 8px 0;color:#333;font-size:18px;">Condividi sessione</h3>' +
        '<p style="margin:0 0 20px 0;color:#666;font-size:14px;">' + title + '</p>';

    // Griglia social buttons
    var socialGrid = document.createElement('div');
    socialGrid.style.cssText = 'display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:20px;';

    // WhatsApp
    socialGrid.innerHTML += '<button onclick="shareWhatsApp(\'' + encodeURIComponent(url) + '\', \'' + encodeURIComponent(shareText) + '\')" style="background:#25D366;border:none;border-radius:12px;padding:15px 10px;cursor:pointer;display:flex;flex-direction:column;align-items:center;gap:5px;">' +
        '<svg width="24" height="24" viewBox="0 0 24 24" fill="white"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>' +
        '<span style="color:#fff;font-size:11px;">WhatsApp</span></button>';

    // Telegram
    socialGrid.innerHTML += '<button onclick="shareTelegram(\'' + encodeURIComponent(url) + '\', \'' + encodeURIComponent(shareText) + '\')" style="background:#0088cc;border:none;border-radius:12px;padding:15px 10px;cursor:pointer;display:flex;flex-direction:column;align-items:center;gap:5px;">' +
        '<svg width="24" height="24" viewBox="0 0 24 24" fill="white"><path d="M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.48.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z"/></svg>' +
        '<span style="color:#fff;font-size:11px;">Telegram</span></button>';

    // Facebook
    socialGrid.innerHTML += '<button onclick="shareFacebook(\'' + encodeURIComponent(url) + '\')" style="background:#1877F2;border:none;border-radius:12px;padding:15px 10px;cursor:pointer;display:flex;flex-direction:column;align-items:center;gap:5px;">' +
        '<svg width="24" height="24" viewBox="0 0 24 24" fill="white"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>' +
        '<span style="color:#fff;font-size:11px;">Facebook</span></button>';

    // X (Twitter)
    socialGrid.innerHTML += '<button onclick="shareTwitter(\'' + encodeURIComponent(url) + '\', \'' + encodeURIComponent(shareText) + '\')" style="background:#000;border:none;border-radius:12px;padding:15px 10px;cursor:pointer;display:flex;flex-direction:column;align-items:center;gap:5px;">' +
        '<svg width="24" height="24" viewBox="0 0 24 24" fill="white"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>' +
        '<span style="color:#fff;font-size:11px;">X</span></button>';

    content.appendChild(socialGrid);

    // Seconda riga: Email e Copia Link
    var secondRow = document.createElement('div');
    secondRow.style.cssText = 'display:grid;grid-template-columns:repeat(2,1fr);gap:12px;margin-bottom:20px;';

    // Email
    secondRow.innerHTML += '<button onclick="shareEmail(\'' + encodeURIComponent(url) + '\', \'' + encodeURIComponent(title) + '\')" style="background:#EA4335;border:none;border-radius:12px;padding:12px;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;">' +
        '<svg width="20" height="20" viewBox="0 0 24 24" fill="white"><path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/></svg>' +
        '<span style="color:#fff;font-size:13px;">Email</span></button>';

    // Copia Link
    secondRow.innerHTML += '<button onclick="copyToClipboard(\'' + url + '\')" style="background:#6c757d;border:none;border-radius:12px;padding:12px;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;">' +
        '<svg width="20" height="20" viewBox="0 0 24 24" fill="white"><path d="M16 1H4c-1.1 0-2 .9-2 2v14h2V3h12V1zm3 4H8c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h11c1.1 0 2-.9 2-2V7c0-1.1-.9-2-2-2zm0 16H8V7h11v14z"/></svg>' +
        '<span style="color:#fff;font-size:13px;">Copia Link</span></button>';

    content.appendChild(secondRow);

    // Native Share (se supportato)
    if (navigator.share) {
        var nativeBtn = document.createElement('button');
        nativeBtn.onclick = function() { shareNative(url, title); };
        nativeBtn.style.cssText = 'width:100%;background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);border:none;border-radius:12px;padding:14px;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;margin-bottom:15px;';
        nativeBtn.innerHTML = '<svg width="20" height="20" viewBox="0 0 24 24" fill="white"><path d="M18 16.08c-.76 0-1.44.3-1.96.77L8.91 12.7c.05-.23.09-.46.09-.7s-.04-.47-.09-.7l7.05-4.11c.54.5 1.25.81 2.04.81 1.66 0 3-1.34 3-3s-1.34-3-3-3-3 1.34-3 3c0 .24.04.47.09.7L8.04 9.81C7.5 9.31 6.79 9 6 9c-1.66 0-3 1.34-3 3s1.34 3 3 3c.79 0 1.5-.31 2.04-.81l7.12 4.16c-.05.21-.08.43-.08.65 0 1.61 1.31 2.92 2.92 2.92s2.92-1.31 2.92-2.92-1.31-2.92-2.92-2.92z"/></svg>' +
            '<span style="color:#fff;font-size:14px;font-weight:500;">Altre opzioni di condivisione...</span>';
        content.appendChild(nativeBtn);
    }

    // Link preview
    var linkPreview = document.createElement('div');
    linkPreview.style.cssText = 'background:#f8f9fa;border-radius:8px;padding:10px;font-size:12px;color:#666;word-break:break-all;';
    linkPreview.textContent = url;
    content.appendChild(linkPreview);

    modal.appendChild(content);
    document.body.appendChild(modal);
}

function closeShareModal() {
    var modal = document.getElementById('share-modal');
    if (modal) modal.remove();
}

function shareWhatsApp(url, text) {
    window.open('https://wa.me/?text=' + text + '%20' + url, '_blank');
    closeShareModal();
}

function shareTelegram(url, text) {
    window.open('https://t.me/share/url?url=' + url + '&text=' + text, '_blank');
    closeShareModal();
}

function shareFacebook(url) {
    window.open('https://www.facebook.com/sharer/sharer.php?u=' + url, '_blank', 'width=600,height=400');
    closeShareModal();
}

function shareTwitter(url, text) {
    window.open('https://twitter.com/intent/tweet?text=' + text + '&url=' + url, '_blank', 'width=600,height=400');
    closeShareModal();
}

function shareEmail(url, title) {
    var subject = encodeURIComponent('Guarda questa sessione live su YalpeR');
    var body = encodeURIComponent('Ciao!\n\nGuarda questa sessione live registrata con YalpeR:\n' + title + '\n\n' + decodeURIComponent(url) + '\n\nBuona visione!');
    window.location.href = 'mailto:?subject=' + subject + '&body=' + body;
    closeShareModal();
}

function shareNative(url, title) {
    if (navigator.share) {
        navigator.share({
            title: 'YalpeR - ' + title,
            text: 'Guarda questa sessione live su YalpeR: ' + title,
            url: url
        }).then(function() {
            closeShareModal();
        }).catch(function(err) {
            console.log('Share cancelled or failed:', err);
        });
    }
}
</script>
