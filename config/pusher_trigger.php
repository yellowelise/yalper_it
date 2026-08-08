<?php

require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once __DIR__ . '/pusher.php';

function yalper_handle_pusher_trigger()
{
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        header('Allow: POST');
        echo json_encode(array('success' => false, 'error' => 'Metodo non consentito.'));
        return;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        http_response_code(400);
        echo json_encode(array('success' => false, 'error' => 'JSON non valido.'));
        return;
    }

    $eventPush = isset($input['event_push']) ? trim((string) $input['event_push']) : '';
    $tag = isset($input['tag']) ? trim((string) $input['tag']) : '';
    $sender = isset($input['sender']) ? trim((string) $input['sender']) : '';

    if (!preg_match('/\A[\p{L}\p{N}_. -]{1,64}\z/uD', $eventPush)) {
        http_response_code(422);
        echo json_encode(array('success' => false, 'error' => 'Evento non valido.'));
        return;
    }
    if ($tag === '' || strlen($tag) > 100 || preg_match('/[\x00-\x1F\x7F]/', $tag)) {
        http_response_code(422);
        echo json_encode(array('success' => false, 'error' => 'Tag non valido.'));
        return;
    }
    if (strlen($sender) > 128 || preg_match('/[\x00-\x1F\x7F]/', $sender)) {
        http_response_code(422);
        echo json_encode(array('success' => false, 'error' => 'Mittente non valido.'));
        return;
    }

    try {
        $pusher = yalper_create_pusher();
        $data = array(
            'event_push' => $eventPush,
            'tag' => $tag,
            'sender' => $sender,
        );
        $pusher->trigger('yalper', 'save-replay-event[' . $eventPush . ']', $data);
        echo json_encode(array('success' => true));
    } catch (Throwable $exception) {
        error_log('Pusher Yalper: ' . $exception->getMessage());
        http_response_code(503);
        echo json_encode(array('success' => false, 'error' => 'Servizio eventi non disponibile.'));
    }
}
