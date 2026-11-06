<?php
/**
 * Endpoint para recibir hasta cinco imágenes y guardarlas en GitHub.
 *
 * Configura GITHUB_TOKEN en el hosting como variable de entorno. El token debe
 * ser fine-grained, limitado al repositorio otype-dcd/XVMarisol y con permiso
 * Contents: Read and write. Nunca pongas el token en el HTML ni en Git.
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

const GITHUB_OWNER = 'otype-dcd';
const GITHUB_REPO = 'XVMarisol';
const GITHUB_BRANCH = 'main';
const IMAGE_FOLDER = 'fotos-fiesta';
const MAX_FILES = 5;
const MAX_FILE_BYTES = 5 * 1024 * 1024; // 5 MB por imagen

function respond(int $status, array $body): never
{
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    respond(405, ['success' => false, 'message' => 'Método no permitido.']);
}

if (!extension_loaded('curl') || !class_exists('finfo')) {
    respond(500, ['success' => false, 'message' => 'El hosting necesita habilitar cURL y Fileinfo en PHP.']);
}

$githubToken = getenv('GITHUB_TOKEN');
if (!$githubToken) {
    respond(500, ['success' => false, 'message' => 'Falta configurar GITHUB_TOKEN en el hosting.']);
}

if (!isset($_FILES['photos'])) {
    respond(400, ['success' => false, 'message' => 'No se recibieron fotos.']);
}

$files = $_FILES['photos'];
// PHP convierte el campo photos[] en arreglos; rechazamos solicitudes mal formadas.
if (!is_array($files['name'] ?? null)) {
    respond(400, ['success' => false, 'message' => 'Formato de archivos inválido.']);
}

$totalFiles = count($files['name']);
if ($totalFiles < 1 || $totalFiles > MAX_FILES) {
    respond(400, ['success' => false, 'message' => 'Selecciona entre 1 y 5 fotos por envío.']);
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$allowedTypes = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp',
];
$uploadedCount = 0;

for ($i = 0; $i < $totalFiles; $i++) {
    $uploadError = $files['error'][$i] ?? UPLOAD_ERR_NO_FILE;
    if ($uploadError !== UPLOAD_ERR_OK) {
        respond(400, ['success' => false, 'message' => 'Una de las fotos no se pudo recibir. Revisa el tamaño permitido por el hosting e inténtalo de nuevo.']);
    }

    $tmpName = $files['tmp_name'][$i] ?? '';
    $fileSize = (int)($files['size'][$i] ?? 0);
    if (!$tmpName || !is_uploaded_file($tmpName) || $fileSize < 1 || $fileSize > MAX_FILE_BYTES) {
        respond(400, ['success' => false, 'message' => 'Cada foto debe pesar menos de 5 MB.']);
    }

    $mimeType = $finfo->file($tmpName);
    if (!isset($allowedTypes[$mimeType]) || @getimagesize($tmpName) === false) {
        respond(400, ['success' => false, 'message' => 'Formato no permitido. Usa imágenes JPG, PNG o WEBP válidas.']);
    }

    $content = file_get_contents($tmpName);
    if ($content === false) {
        respond(500, ['success' => false, 'message' => 'No se pudo leer una de las fotos.']);
    }

    $filename = gmdate('Ymd-His') . '-' . bin2hex(random_bytes(8)) . '.' . $allowedTypes[$mimeType];
    $path = IMAGE_FOLDER . '/' . $filename;
    $payload = json_encode([
        'message' => 'Foto de invitado: ' . $filename,
        'content' => base64_encode($content),
        'branch' => GITHUB_BRANCH,
    ]);

    $url = 'https://api.github.com/repos/' . GITHUB_OWNER . '/' . GITHUB_REPO . '/contents/' . implode('/', array_map('rawurlencode', explode('/', $path)));
    $curl = curl_init($url);
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => 'PUT',
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 45,
        CURLOPT_HTTPHEADER => [
            'Accept: application/vnd.github+json',
            'Authorization: Bearer ' . $githubToken,
            'X-GitHub-Api-Version: 2022-11-28',
            'User-Agent: XVMarisol-Photo-Upload',
            'Content-Type: application/json',
        ],
    ]);

    $response = curl_exec($curl);
    $httpCode = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    if ($response === false || $httpCode !== 201) {
        // No devolvemos al visitante el cuerpo de GitHub ni datos internos.
        error_log('GitHub upload failed with HTTP status ' . $httpCode);
        respond(502, ['success' => false, 'message' => 'GitHub no pudo guardar una foto. Inténtalo nuevamente.']);
    }

    $uploadedCount++;
}

respond(200, [
    'success' => true,
    'message' => '¡Se subieron ' . $uploadedCount . ' foto(s) correctamente! Gracias por compartir tus recuerdos.',
]);
