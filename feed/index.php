<?php
session_start();
$logged = isset($_SESSION['logged']) && $_SESSION['logged'] == 1;
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="google-adsense-account" content="ca-pub-9880317414365518">
    <title>Profilo Utente</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/hls.js@latest"></script>
	<script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-9880317414365518" crossorigin="anonymous"></script>    
	<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <style>
        .video-container {
            position: relative;
        }
        .play-overlay {
            cursor: pointer;
            transition: background-color 0.3s;
        }
        .play-overlay:hover {
            background-color: rgba(0, 0, 0, 0.6) !important;
        }
        .navbar {
            z-index: 1050;
        }
        
// Aggiungi questo stile nella sezione <style> esistente
.tag-cloud .badge {
    font-size: 0.9rem;
    padding: 0.5rem 0.8rem;
    transition: all 0.2s;
}

.tag-cloud .badge:hover {
    background-color: #dc3545 !important;
}

.tag-cloud .badge i {
    margin-left: 5px;
}        
    </style>
</head>
<body>
<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-light bg-light sticky-top">
    <div class="container-fluid">
        <a class="navbar-brand" href="#">
            <img src="../favicons/favicon180.png" alt="Logo" width="30" height="30" class="d-inline-block align-top">
            Feed / Bacheca
        </a>
        <div class="d-flex">
            <?php if ($logged): ?>
                <a href="./logout.php" class="btn btn-outline-danger">Logout</a>
            <?php else: ?>
                <a href="./login.php" class="btn btn-outline-primary">Login</a>
            <?php endif; ?>
        </div>
    </div>
</nav>

<div class="container mt-4">
    <?php if ($logged): ?>
        <!-- Sezione Profilo -->
        <div class="card mb-4">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-2">
						<?php 
							if (file_exists("../img/". $_SESSION['id']."/logo.png"))
								echo '<img src="../img/'. $_SESSION['id'].'/logo.png" class="img-fluid rounded-circle">';
							else
								echo '<img src="../favicons/favicon180.png" class="img-fluid rounded-circle">';
							?>	
                    </div>
                    <div class="col-md-10">
                        <h2 id="userName"><?php echo $_SESSION['firstname'] . " " . $_SESSION['lastname']; ?></h2>
                        <p id="userEmail"><?php echo $_SESSION['email']; ?></p>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Tab Navigation -->
    <ul class="nav nav-tabs" id="myTab" role="tablist">
        <li class="nav-item">
            <a class="nav-link active" id="feed-tab" data-bs-toggle="tab" href="#feed"><?php echo !$logged ? 'Video pubblici' : 'Il mio feed'; ?></a>
        </li>
        <?php if ($logged): ?>
            <li class="nav-item">
                <a class="nav-link" id="videos-tab" data-bs-toggle="tab" href="#videos">I miei video</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="friends-tab" data-bs-toggle="tab" href="#friends">Aggiungi amico/link</a>
            </li>
        <?php endif; ?>
    </ul>

    <!-- Tab Content -->
    <div class="tab-content mt-3" id="myTabContent">
        <!-- Il mio feed -->
        <div class="tab-pane fade show active" id="feed">
			<div class="tag-cloud mb-3" id="filterTagCloud" style="display:none;">
				<h6>Filtri attivi:</h6>
					<div id="filterTags"></div>
			</div>
    			
            <div class="row"  id="feedContainer"></div>


        </div>
                <?php if ($logged): ?>
            <!-- I miei video -->
            <div class="tab-pane fade" id="videos">
                <div class="row" id="videoContainer"></div>
            </div>

            <!-- Aggiungi amico/link -->
            <div class="tab-pane fade" id="friends">
                <div class="row">
                    <div class="col-md-6">
                        <h4>Aggiungi link</h4>
                        <form id="linkForm">
                            <div class="mb-3">
                                <input type="url" class="form-control" placeholder="Incolla il link qui">
                            </div>
                            <button type="submit" class="btn btn-primary">Aggiungi Link</button>
                        </form>
                    </div>
                    <div class="col-md-6">
                        <h4>Cerca persone</h4>
                        <div class="input-group mb-3">
                            <input type="text" class="form-control" placeholder="Cerca persone...">
                            <button class="btn btn-outline-secondary" type="button">Cerca</button>
                        </div>
                        <div id="searchResults"></div>
                    </div>
                </div>
            </div>
        <?php endif; ?>


    </div>
</div>


<!-- Modal -->
<div class="modal fade" id="videoModal" tabindex="-1" aria-labelledby="videoModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-fullscreen">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <iframe id="sharingFrame" style="width:100%;height:100%;border:none;"></iframe>
            </div>
        </div>
    </div>
</div>
</body>
</html>

<script>
// Script JavaScript come nel codice originale
$(document).ready(function() {
        loadUserFeed();

});

const USER_DATA = {
    id: <?php echo json_encode(isset($_SESSION['id']) ? (int) $_SESSION['id'] : 0); ?>
};

function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, character => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
    })[character]);
}

function loadUserFeed() {
    const data = {
        ...USER_DATA,
        maker_token: new URLSearchParams(window.location.search).get('token')
    };
    
    $.ajax({
        url: '../API/feed.php',
        method: 'GET',
        data: data,
        success: function(response) {
            // Gestione filtri
            //console.log(response.filter);
            //alert(response.filter.length);
            if (response.filter && response.filter.length > 0) {
                const tagHtml = response.filter.map(filter => 
                    `<span class="badge bg-primary me-2 mb-2" style="cursor:pointer" 
                           data-token="${escapeHtml(filter.token)}" data-utente="${escapeHtml(filter.utente)}">
                        ${escapeHtml(filter.utente)} <i class="bi bi-x"></i>
                    </span>`
                ).join('');
                
                $('#filterTagCloud').show();
                $('#filterTags').html(tagHtml);
                
                // Gestione click per rimozione tag
                $('.badge').on('click', function() {
                    const token = $(this).data('token');
                    const params = new URLSearchParams(window.location.search);
                    params.delete('token');
                    window.history.replaceState({}, '', `${window.location.pathname}${params.toString() ? '?' + params.toString() : ''}`);
                    loadUserFeed();
                });
            } else {
                $('#filterTagCloud').hide();
            }

            // Gestione visualizzazione video
            if (response.success && response.videos) {
                let videoHTML = '';
                
                response.videos.forEach((video) => {
                    if (video && video.video_paths.length > 0) {
                        const thumbnailPath = '../' + video.video_paths[0].replace('.m3u8', '_wall.jpg');
                        videoHTML += `
                            <div class="col-12 col-sm-6 col-lg-3 mb-4">
                                <div class="card">
                                    <div class="card-img-top video-container" style="height: 200px;" data-shorter="${video.shorter}">
                                         <img src="${escapeHtml(thumbnailPath)}" class="w-100 h-100" style="object-fit: cover;" alt="Video thumbnail">
                                        <div class="play-overlay position-absolute top-0 start-0 w-100 h-100 d-flex justify-content-center align-items-center" 
                             style="background-color: rgba(0,0,0,0.3);">
                                            <i class="bi bi-play-circle text-white" style="font-size: 3rem;"></i>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <h5 class="card-title">
                                            <a href="#" class="video-title-link text-decoration-none" data-shorter="${video.shorter}">
                                                 ${escapeHtml(video.title || 'Senza titolo')}
                                            </a>
                                        </h5>
                                        <p class="card-text">
                                            <small class="text-muted">
                                                 Video Maker: <a href="index.php?token=${encodeURIComponent(video.maker_token)}" class="text-decoration-none">${escapeHtml(video.utente)}</a><br>
                                                Visualizzazioni: ${video.click}<br>
                                                Creato il: ${video.creation_date}<br>
                                                Stato: ${video.visibility_text}
                                            </small>
                                        </p>
                                        <div class="text-center">
                                            <button class="btn btn-outline-primary btn-sm" 
                                                    onclick="copyToClipboard('https://yalper.it/sharing.php?fs=${video.shorter}')">
                                                Copia link
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        `;
                    }
                });

                $('#feedContainer').html(videoHTML || '<div class="alert alert-info">Nessun video trovato</div>');
                
                // Funzione per aprire il modal
                function openVideoModal(shorter) {
                    const sharingUrl = `../sharing.php?fs=${shorter}`;
                    $('#sharingFrame').attr('src', sharingUrl);
                    const videoModal = new bootstrap.Modal(document.getElementById('videoModal'));
                    videoModal.show();
                }
                
                // Gestione click sui titoli
                $('.video-title-link').on('click', function(e) {
                    e.preventDefault();
                    openVideoModal($(this).data('shorter'));
                });
                
                // Gestione click sull'immagine e overlay
                $('.video-container').on('click', function() {
                    openVideoModal($(this).data('shorter'));
                });
            } else {
                $('#feedContainer').html('<div class="alert alert-danger">Errore nel caricamento dei video</div>');
            }
        },
        error: function(xhr, status, error) {
            console.error('Errore Ajax:', error);
            $('#feedContainer').html('<div class="alert alert-danger">Errore di connessione</div>');
        }
    });
}






function loadUserVideos() {
    const data = {
        ...USER_DATA
    };
    
    $.ajax({
        url: '../API/my_video.php',
        method: 'GET',
        data: data,
        success: function(response) {
            // Gestione filtri
            //console.log(response.filter);
            //alert(response.filter.length);
            // Gestione visualizzazione video
            if (response.success && response.videos) {
                let videoHTML = '';
                
                response.videos.forEach((video) => {
                    if (video && video.video_paths.length > 0) {
                        const thumbnailPath = '../' + video.video_paths[0].replace('.m3u8', '_wall.jpg');
                        videoHTML += `
                            <div class="col-12 col-sm-6 col-lg-3 mb-4">
                                <div class="card">
                                    <div class="card-img-top video-container" style="height: 200px;" data-shorter="${video.shorter}">
                                         <img src="${escapeHtml(thumbnailPath)}" class="w-100 h-100" style="object-fit: cover;" alt="Video thumbnail">
                                        <div class="play-overlay position-absolute top-0 start-0 w-100 h-100 d-flex justify-content-center align-items-center" 
                             style="background-color: rgba(0,0,0,0.3);">
                                            <i class="bi bi-play-circle text-white" style="font-size: 3rem;"></i>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <h5 class="card-title">
                                            <a href="#" class="video-title-link text-decoration-none" data-shorter="${video.shorter}">
                                                 ${escapeHtml(video.title || 'Senza titolo')}
                                            </a>
                                        </h5>
                                        <p class="card-text">
                                            <small class="text-muted">
                                                 Video Maker: <a href="index.php?token=${encodeURIComponent(video.maker_token)}" class="text-decoration-none">${escapeHtml(video.utente)}</a><br>
                                                Visualizzazioni: ${video.click}<br>
                                                Creato il: ${video.creation_date}<br>
                                                Stato: ${video.visibility_text}
                                            </small>
                                        </p>
                                        <div class="text-center">
                                            <button class="btn btn-outline-primary btn-sm" 
                                                    onclick="copyToClipboard('https://yalper.it/sharing.php?fs=${video.shorter}')">
                                                Copia link
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        `;
                    }
                });

                $('#videoContainer').html(videoHTML || '<div class="alert alert-info">Nessun video trovato</div>');
                
                // Funzione per aprire il modal
                function openVideoModal(shorter) {
                    const sharingUrl = `../sharing.php?fs=${shorter}`;
                    $('#sharingFrame').attr('src', sharingUrl);
                    const videoModal = new bootstrap.Modal(document.getElementById('videoModal'));
                    videoModal.show();
                }
                
                // Gestione click sui titoli
                $('.video-title-link').on('click', function(e) {
                    e.preventDefault();
                    openVideoModal($(this).data('shorter'));
                });
                
                // Gestione click sull'immagine e overlay
                $('.video-container').on('click', function() {
                    openVideoModal($(this).data('shorter'));
                });
            } else {
                $('#videoContainer').html('<div class="alert alert-danger">Errore nel caricamento dei video</div>');
            }
        },
        error: function(xhr, status, error) {
            console.error('Errore Ajax:', error);
            $('#videoContainer').html('<div class="alert alert-danger">Errore di connessione</div>');
        }
    });
}





document.getElementById('videoModal').addEventListener('hidden.bs.modal', function () {
    document.getElementById('sharingFrame').src = '';
});


function initializeHLSPlayer(videoElement, source) {
    console.log("Initializing HLS player with source:", source);
    if (Hls.isSupported()) {
        const hls = new Hls({
            debug: true,  // Abilita il debug
            enableWorker: true
        });
        
        hls.on(Hls.Events.ERROR, function(event, data) {
            console.error('HLS Error:', data);
        });

        hls.on(Hls.Events.MANIFEST_LOADING, function() {
            console.log('Manifest loading...');
        });

        hls.on(Hls.Events.MANIFEST_LOADED, function() {
            console.log('Manifest loaded');
        });

        hls.loadSource(source);
        hls.attachMedia(videoElement);
        
        hls.on(Hls.Events.MANIFEST_PARSED, function() {
            console.log('Manifest parsed');
        });
    } else if (videoElement.canPlayType('application/vnd.apple.mpegurl')) {
        videoElement.src = source;
    } else {
        console.error('HLS not supported');
    }
}





function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => {
        alert('Link copiato negli appunti!');
    }).catch(err => {
        console.error('Errore durante la copia:', err);
        alert('Errore durante la copia del link');
    });
}

// Gestione ricerca persone
$('.input-group button').click(function() {
    let searchTerm = $('.input-group input').val();
    if (searchTerm.trim()) {
        $.ajax({
            url: '../API/search_users.php',
            method: 'GET',
            data: { ...USER_DATA, search: searchTerm },
            success: function(response) {
                if (response.success) {
                    let resultsHTML = '';
                    response.users.forEach(user => {
                        resultsHTML += `
                            <div class="card mb-2">
                                <div class="card-body d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-0">${user.firstname} ${user.lastname}</h6>
                                        <small class="text-muted">${user.email}</small>
                                    </div>
                                    <button class="btn btn-primary btn-sm add-friend" data-user-id="${user.id}">
                                        Aggiungi
                                    </button>
                                </div>
                            </div>
                        `;
                    });
                    $('#searchResults').html(resultsHTML);
                }
            }
        });
    }
});

// Gestione form link
$('#linkForm').submit(function(e) {
    e.preventDefault();
    let link = $(this).find('input[type="url"]').val();
    
    if (link) {
        $.ajax({
            url: '../API/add_link.php',
            method: 'POST',
            data: { ...USER_DATA, link: link },
            success: function(response) {
                if (response.success) {
                    alert('Link aggiunto con successo!');
                    $('#linkForm')[0].reset();
                } else {
                    alert('Errore nell\'aggiunta del link');
                }
            }
        });
    }
});

// Eventi tab
$('a[data-bs-toggle="tab"]').on('shown.bs.tab', function (e) {
    if (e.target.id === 'videos-tab') {
        loadUserVideos();
    } else if (e.target.id === 'feed-tab') {
        loadUserFeed();
    }
});

</script>
