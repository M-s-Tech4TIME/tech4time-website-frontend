<?php
/**
 * Tech4TIME — where the backend's pictures arrive.
 *
 * FRONTEND ONLY, and the second of exactly two things on this host that write
 * anything. api/publish.php writes content/; this writes uploads/.
 *
 * WHY THERE ARE TWO ENDPOINTS AND NOT ONE
 * The content channel is one signed JSON document capped at a megabyte. Sixty
 * logos base64'd into it would blow that cap and re-send every picture on every
 * save of a single word. So a picture travels on its own road, with the same
 * discipline: same key, same fingerprint, same five-minute window, same
 * vocabulary of refusals — ADR 0019.
 *
 * WHAT ARRIVES IS ALREADY OURS.
 * The backend does not forward what somebody uploaded. It decodes the picture
 * and re-encodes it through gd, so what is posted here is bytes that library
 * wrote — no EXIF, no trailing payload, no polyglot. This side does not take
 * that on trust; it reads the header itself and refuses anything that is not
 * one of three raster formats.
 *
 * THE NAME IS NEVER SENT.
 * It is computed here from the bytes — sixteen hex characters of SHA-256 and
 * an extension chosen from the DETECTED type. The sender's idea of what the
 * file is called never reaches a filesystem call, so there is nothing for a
 * traversal or a .php extension to ride in on. That is the first of three
 * independent layers; the second is that the bytes were re-encoded, and the
 * third is the .htaccess allow-list, which serves that shape and nothing else.
 *
 * IDEMPOTENT. The same picture twice is one file. A re-send after a lost
 * response answers 200 without writing again, which is what makes
 * tools/reconcile.py in the backend safe to run whenever.
 *
 * WHAT IT ANSWERS
 *   200  {"ok":true,  "asset":"a1b2c3d4e5f60718.webp", "width":320, "height":167}
 *   4xx  {"ok":false, "code":"not-an-image"}
 */

declare(strict_types=1);

require_once __DIR__ . '/../lib/publish.php';

/** Where a published picture lands. Outside content/, and served. */
const PUBLISH_ASSET_DIR = __DIR__ . '/../uploads';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');

function asset_answer(int $status, array $body): never
{
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function asset_refuse(int $status, string $code): never
{
    asset_answer($status, [
        'ok'    => false,
        'code'  => $code,
        'error' => publish_reason($code),
    ]);
}

/* ------------------------------------------------------------ the request */

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    asset_refuse(405, 'method-not-allowed');
}

/* Before reading the body. Content-Length is the sender's claim, and is
   checked again below against what actually arrived. */
if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0) > PUBLISH_ASSET_MAX_BYTES) {
    asset_refuse(413, 'asset-too-large');
}

$body = (string)file_get_contents('php://input', false, null, 0,
                                  PUBLISH_ASSET_MAX_BYTES + 1);

if (strlen($body) > PUBLISH_ASSET_MAX_BYTES) {
    asset_refuse(413, 'asset-too-large');
}

/* The store or the key is missing. Said plainly rather than as a signature
   failure, because they are different problems with different fixes. */
$problem = publish_problem();
if ($problem !== '') {
    asset_refuse(503, 'not-configured');
}

/* Verified against the RAW BYTES, before anything looks at them. Same rule as
   the content endpoint: the signature covers what arrived. */
$fault = publish_verify(
    $body,
    (string)($_SERVER['HTTP_X_T4T_SIGNATURE'] ?? ''),
    (string)($_SERVER['HTTP_X_T4T_TIMESTAMP'] ?? '')
);

if ($fault !== '') {
    asset_refuse(401, $fault);
}

/* ------------------------------------------------------ what it actually is */

$kind = publish_asset_type($body);

if ($kind === null) {
    asset_refuse(415, 'not-an-image');
}

[$ext, $mime, $width, $height] = $kind;

/* Only now is there a name, and it is this side's. */
$name = publish_asset_name($body, $ext);
$path = PUBLISH_ASSET_DIR . '/' . $name;

if (is_file($path) && hash_file('sha256', $path) === hash('sha256', $body)) {
    /* Already here, byte for byte. A re-send after a lost response, or the
       same picture uploaded twice. Nothing to do, and saying so is not an
       error — reconcile.py leans on this. */
    asset_answer(200, ['ok' => true, 'asset' => $name, 'held' => true,
                       'width' => $width, 'height' => $height]);
}

/* --------------------------------------------------------------- the write */

if (!is_dir(PUBLISH_ASSET_DIR) && !@mkdir(PUBLISH_ASSET_DIR, 0755, true)
        && !is_dir(PUBLISH_ASSET_DIR)) {
    asset_refuse(500, 'asset-write-failed');
}

/* Written to a temporary name in the same directory and renamed, so a reader
   never sees half a picture — the rule lib/store.php follows for the same
   reason. Same directory because rename() is only atomic within a filesystem. */
$tmp = PUBLISH_ASSET_DIR . '/.' . bin2hex(random_bytes(8)) . '.tmp';

if (@file_put_contents($tmp, $body) !== strlen($body) || !@rename($tmp, $path)) {
    @unlink($tmp);
    asset_refuse(500, 'asset-write-failed');
}

@chmod($path, 0644);

asset_answer(200, ['ok' => true, 'asset' => $name, 'held' => false,
                   'width' => $width, 'height' => $height, 'type' => $mime]);
