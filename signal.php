<?php
// signal.php - Simple PHP signaling server for WebRTC
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

session_start();

$action = $_GET['action'] ?? '';
$room = preg_replace('/[^a-zA-Z0-9]/', '', $_GET['room'] ?? '');
$peerId = preg_replace('/[^a-zA-Z0-9\-]/', '', $_GET['peerId'] ?? '');
$data = file_get_contents('php://input');

// Store files in tmp directory (writable on InfinityFree)
$baseDir = sys_get_temp_dir() . '/wordbomb_';
if (!file_exists($baseDir)) mkdir($baseDir, 0777, true);

switch($action) {
    case 'create':
        // Host creates a room
        $roomFile = $baseDir . $room . '.json';
        $roomData = [
            'host' => $peerId,
            'client' => null,
            'hostOffer' => null,
            'clientAnswer' => null,
            'hostCandidates' => [],
            'clientCandidates' => [],
            'created' => time()
        ];
        file_put_contents($roomFile, json_encode($roomData));
        echo json_encode(['success' => true, 'room' => $room]);
        break;
        
    case 'join':
        // Client joins a room
        $roomFile = $baseDir . $room . '.json';
        if (!file_exists($roomFile)) {
            echo json_encode(['error' => 'Room not found']);
            exit;
        }
        $roomData = json_decode(file_get_contents($roomFile), true);
        if ($roomData['client']) {
            echo json_encode(['error' => 'Room full']);
            exit;
        }
        $roomData['client'] = $peerId;
        file_put_contents($roomFile, json_encode($roomData));
        echo json_encode(['success' => true, 'host' => $roomData['host']]);
        break;
        
    case 'offer':
        // Host sends offer
        $roomFile = $baseDir . $room . '.json';
        $roomData = json_decode(file_get_contents($roomFile), true);
        $roomData['hostOffer'] = json_decode($data, true);
        file_put_contents($roomFile, json_encode($roomData));
        echo json_encode(['success' => true]);
        break;
        
    case 'getOffer':
        // Client polls for offer
        $roomFile = $baseDir . $room . '.json';
        $roomData = json_decode(file_get_contents($roomFile), true);
        echo json_encode(['offer' => $roomData['hostOffer']]);
        break;
        
    case 'answer':
        // Client sends answer
        $roomFile = $baseDir . $room . .json';
        $roomData = json_decode(file_get_contents($roomFile), true);
        $roomData['clientAnswer'] = json_decode($data, true);
        file_put_contents($roomFile, json_encode($roomData));
        echo json_encode(['success' => true]);
        break;
        
    case 'getAnswer':
        // Host polls for answer
        $roomFile = $baseDir . $room . '.json';
        $roomData = json_decode(file_get_contents($roomFile), true);
        echo json_encode(['answer' => $roomData['clientAnswer']]);
        break;
        
    case 'candidate':
        // Exchange ICE candidates
        $roomFile = $baseDir . $room . '.json';
        $roomData = json_decode(file_get_contents($roomFile), true);
        $candidate = json_decode($data, true);
        
        if ($peerId === $roomData['host']) {
            $roomData['hostCandidates'][] = $candidate;
        } else {
            $roomData['clientCandidates'][] = $candidate;
        }
        file_put_contents($roomFile, json_encode($roomData));
        echo json_encode(['success' => true]);
        break;
        
    case 'getCandidates':
        // Get remote candidates
        $roomFile = $baseDir . $room . '.json';
        $roomData = json_decode(file_get_contents($roomFile), true);
        
        if ($peerId === $roomData['host']) {
            echo json_encode(['candidates' => $roomData['clientCandidates']]);
        } else {
            echo json_encode(['candidates' => $roomData['hostCandidates']]);
        }
        break;
        
    case 'status':
        // Check room status
        $roomFile = $baseDir . $room . '.json';
        if (!file_exists($roomFile)) {
            echo json_encode(['exists' => false]);
            exit;
        }
        $roomData = json_decode(file_get_contents($roomFile), true);
        echo json_encode([
            'exists' => true,
            'hasClient' => !!$roomData['client'],
            'hasOffer' => !!$roomData['hostOffer'],
            'hasAnswer' => !!$roomData['clientAnswer']
        ]);
        break;
        
    case 'cleanup':
        // Clean old rooms
        $files = glob($baseDir . '*.json');
        $now = time();
        foreach ($files as $file) {
            $data = json_decode(file_get_contents($file), true);
            if ($now - $data['created'] > 3600) { // 1 hour
                unlink($file);
            }
        }
        echo json_encode(['cleaned' => true]);
        break;
        
    default:
        echo json_encode(['error' => 'Unknown action']);
}
?>
