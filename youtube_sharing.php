<?php
session_start();

header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
include('config/db.php');

error_reporting(E_ALL);
ini_set("display_errors", 1);

// Generiamo un nome file casuale per evitare conflitti
$shorter = $_REQUEST['shorter']; //8yLWirq9axbL0uNi41drHPtCG0Xr2n
$path = (__DIR__);



// Prepare statement to get the user's specific reactions
$stmt = $connection->prepare("SELECT * FROM url_shorter WHERE shorter = ?");
$stmt->bind_param("s", $shorter);
$stmt->execute();
$result = $stmt->get_result();


if($result->num_rows > 0) {
    $row = $result->fetch_assoc();
	
	$links = explode("|",$row['long_link']);
	
	if ($links)
		{
		for ($i=0;$i<count($links);$i++)
			{
				$output_file = $path . DIRECTORY_SEPARATOR . str_replace(".m3u8",".mp4",base64_decode($links[$i]));
				$json = file_get_contents($path . DIRECTORY_SEPARATOR . str_replace(".m3u8",".json",base64_decode($links[$i])));
				$json_data = json_decode($json,true);
							
			//if ($upload_to_youtube) 
			{
				// Ottieni i parametri per il caricamento YouTube
				$video_title = isset($json_data['partita']) ? $json_data['partita'] : 'Video YalpeR ' . date('Y-m-d H:i:s');
				$video_description = isset($json_data['partita']) ? $json_data['partita'] . " " . $json_data['text_home'] . " - " . $json_data['text_away'] : 'Video caricato tramite YalpeR.it';
				
				$video_tags = isset($_POST['video_tags']) ? $_POST['video_tags'] : array('YalpeR');
				
				$video_privacy = isset($_POST['video_privacy']) ? $_POST['video_privacy'] : 'public'; // public, private, unlisted
				
				try {
					// Carica il video su YouTube
					$youtube_url = uploadToYouTube(
						$output_file,
						$video_title,
						$video_description,
						$video_tags,
						$video_privacy
					);
					
					// Prepara la risposta con l'URL di YouTube
					$response = [
						'success' => true,
						'youtube_url' => $youtube_url,
						'message' => 'Video caricato con successo su YouTube'
					];
					
					unlink($path . DIRECTORY_SEPARATOR . str_replace(".m3u8",".json",base64_decode($links[$i])));
					// Invia la risposta JSON
					echo json_encode($response);
					
				} catch (Exception $e) {
					// Controlla se l'errore è un richiesta di autenticazione
					if (strpos($e->getMessage(), 'auth_required:') === 0) {
						$auth_url = substr($e->getMessage(), 13); // Rimuovi il prefisso "auth_required:"
						
						// Reindirizza all'URL di autenticazione o restituisci una risposta JSON
						$response = [
							'auth_required' => true,
							'auth_url' => $auth_url,
							'message' => 'È necessaria l\'autenticazione a YouTube. Clicca sul link per autorizzare l\'applicazione.'
						];
						
						echo json_encode($response);
						
						// Non cancellare il file in caso di autenticazione richiesta
						// perché lo useremo dopo l'autenticazione
					} else {
						// In caso di altro errore nel caricamento su YouTube
						http_response_code(500);
						echo json_encode([
							'error' => 'Errore durante il caricamento su YouTube',
							'message' => $e->getMessage()
						]);
						
						// Rimuovi il file dopo l'errore
						//unlink($output_file);
					}
				}
			}
			}
		}
}

/**
 * Funzione per caricare un video su YouTube
 * 
 * @param string $video_path Percorso completo al file video
 * @param string $title Titolo del video
 * @param string $description Descrizione del video
 * @param array $tags Tag del video
 * @param string $privacy_status Stato della privacy (public, private, unlisted)
 * @return string URL del video caricato
 */
function uploadToYouTube($video_path, $title, $description, $tags, $privacy_status) {
    // Verifica che il file esista
    if (!file_exists($video_path)) {
        throw new Exception('Il file video non esiste: ' . $video_path);
    }

    // Includi la libreria Google API client
    require_once 'vendor/autoload.php'; // Assicurati di avere installato google/apiclient tramite composer

    // Crea un client Google
    $client = new Google_Client();
    $client->setAuthConfig('/home/yalperit/.config/yalper/google_client_secret.json');
    $client->setScopes([
        'https://www.googleapis.com/auth/youtube.upload',
        'https://www.googleapis.com/auth/youtube'
    ]);
    $client->setAccessType('offline');
    $client->setPrompt('consent'); // Per ricevere sempre il refresh token

    // Carica il token di accesso precedentemente salvato, se esiste
    $tokenPath = '/home/yalperit/.config/yalper/youtube_token.json';
    if (file_exists($tokenPath)) {
        $accessToken = json_decode(file_get_contents($tokenPath), true);
        $client->setAccessToken($accessToken);
    }

    // Se il token è scaduto, aggiornalo
    if ($client->isAccessTokenExpired()) {
        if ($client->getRefreshToken()) {
            $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
            // Salva il nuovo token
            file_put_contents($tokenPath, json_encode($client->getAccessToken()));
        } else {
            // Se non hai un refresh token, devi ottenere un nuovo token di autorizzazione
            // Crea l'URL di autenticazione e reindirizza l'utente
            $authUrl = $client->createAuthUrl();
            
            // Salva i dati correnti in sessione per riprenderli dopo l'autenticazione
            $_SESSION['youtube_upload_pending'] = [
                'video_path' => $video_path,
                'title' => $title,
                'description' => $description,
                'tags' => $tags,
                'privacy_status' => $privacy_status
            ];
            
            // Restituisci un messaggio JSON con l'URL di autenticazione
            throw new Exception('auth_required:' . $authUrl);
        }
    }

    // Crea un servizio YouTube
    $youtube = new Google_Service_YouTube($client);

    // Prepara i metadati del video
    $snippet = new Google_Service_YouTube_VideoSnippet();
    $snippet->setTitle($title);
    $snippet->setDescription($description);
    $snippet->setTags($tags);
    $snippet->setCategoryId("22"); // Categoria 22 è "People & Blogs"

    // Imposta lo stato di privacy
    $status = new Google_Service_YouTube_VideoStatus();
    $status->setPrivacyStatus($privacy_status);

    // Crea l'oggetto video
    $video = new Google_Service_YouTube_Video();
    $video->setSnippet($snippet);
    $video->setStatus($status);

    // Esegui l'upload
    try {
        // Prepara il file per l'upload
        $chunkSizeBytes = 1 * 1024 * 1024; // 1MB
        
        // Crea una richiesta di upload
        $client->setDefer(true);
        $insertRequest = $youtube->videos->insert('snippet,status', $video);
        $media = new Google_Http_MediaFileUpload(
            $client,
            $insertRequest,
            'video/*',
            null,
            true,
            $chunkSizeBytes
        );
        $media->setFileSize(filesize($video_path));
        
        // Esegui l'upload a blocchi
        $status = false;
        $handle = fopen($video_path, "rb");
        while (!$status && !feof($handle)) {
            $chunk = fread($handle, $chunkSizeBytes);
            $status = $media->nextChunk($chunk);
        }
        fclose($handle);
        
        // Reset per ulteriori richieste
        $client->setDefer(false);
        
        // Restituisci l'URL del video caricato
        return 'https://www.youtube.com/watch?v=' . $status['id'];
    } catch (Google_Service_Exception $e) {
        throw new Exception('Errore nel servizio Google: ' . $e->getMessage());
    } catch (Google_Exception $e) {
        throw new Exception('Errore nel client Google: ' . $e->getMessage());
    }
}
?>
