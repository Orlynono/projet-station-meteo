<?php
/**
 * receive_data.php
 * Reçoit les données JSON des capteurs (ESP32) via HTTP GET
 * Déposer ce fichier dans : /var/www/html/meteo/
 *
 * URL d'appel depuis l'ESP32 :
 * http://<IP_RASPBERRY>/meteo/receive_data.php?data={"temp":22.5,"hum":60,...}
 *
 * OU avec paramètres séparés :
 * http://<IP_RASPBERRY>/meteo/receive_data.php?temp=22.5&hum=60&pression=1013&pluie=0.2&vent=12&dir=NE&aqi=35&lux=3200
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Chemin du fichier de stockage des données
define('DATA_FILE', __DIR__ . '/data.json');

// Clé secrète optionnelle pour sécuriser l'endpoint (à définir aussi dans l'ESP32)
define('SECRET_KEY', ''); // ex: 'mon_secret_123' — laisser vide pour désactiver

// --- Vérification clé secrète ---
if (SECRET_KEY !== '') {
    $key = $_GET['key'] ?? '';
    if ($key !== SECRET_KEY) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Clé invalide']);
        exit;
    }
}

// --- Lecture des données ---
// Mode 1 : JSON encodé dans le paramètre "data"
if (isset($_GET['data'])) {
    $raw = urldecode($_GET['data']);
    $capteurs = json_decode($raw, true);
    if (!$capteurs) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'JSON invalide']);
        exit;
    }
}
// Mode 2 : paramètres GET séparés
else {
    $capteurs = [];
    $capteurs['temp']     = isset($_GET['temp'])     ? (float)$_GET['temp']     : null;
    $capteurs['hum']      = isset($_GET['hum'])      ? (float)$_GET['hum']      : null;
    $capteurs['pression'] = isset($_GET['pression']) ? (float)$_GET['pression'] : null;
    $capteurs['pluie']    = isset($_GET['pluie'])    ? (float)$_GET['pluie']    : null;
    $capteurs['vent']     = isset($_GET['vent'])     ? (float)$_GET['vent']     : null;
    $capteurs['dir']      = isset($_GET['dir'])      ? htmlspecialchars($_GET['dir']) : null;
    $capteurs['aqi']      = isset($_GET['aqi'])      ? (int)$_GET['aqi']        : null;
    $capteurs['lux']      = isset($_GET['lux'])      ? (int)$_GET['lux']        : null;
}

// --- Validation basique ---
$erreurs = [];
if ($capteurs['temp'] !== null && ($capteurs['temp'] < -50 || $capteurs['temp'] > 80))
    $erreurs[] = 'Température hors limites';
if ($capteurs['hum'] !== null && ($capteurs['hum'] < 0 || $capteurs['hum'] > 100))
    $erreurs[] = 'Humidité hors limites';
if ($capteurs['pression'] !== null && ($capteurs['pression'] < 850 || $capteurs['pression'] > 1100))
    $erreurs[] = 'Pression hors limites';

if (!empty($erreurs)) {
    http_response_code(422);
    echo json_encode(['status' => 'error', 'message' => implode(', ', $erreurs)]);
    exit;
}

// --- Construction du JSON à sauvegarder ---
$data = [
    'timestamp'  => date('Y-m-d H:i:s'),
    'temp'       => $capteurs['temp'],
    'hum'        => $capteurs['hum'],
    'pression'   => $capteurs['pression'],
    'pluie'      => $capteurs['pluie'],
    'vent'       => $capteurs['vent'],
    'dir'        => $capteurs['dir'],
    'aqi'        => $capteurs['aqi'],
    'lux'        => $capteurs['lux'],
];

// --- Sauvegarde ---
$result = file_put_contents(DATA_FILE, json_encode($data, JSON_PRETTY_PRINT));

if ($result === false) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Impossible d\'écrire data.json — vérifier les permissions']);
    exit;
}

// --- Réponse succès ---
echo json_encode(['status' => 'ok', 'received' => $data]);
