<?php
// includes/mailer.php

function fundocol_is_local_env(): bool {
    $host = $_SERVER['HTTP_HOST'] ?? '';
    return (strpos($host, 'localhost') !== false || strpos($host, '127.0.0.1') !== false);
}

function graph_get_token(array $cfg): string {
    $tokenUrl = "https://login.microsoftonline.com/{$cfg['tenant_id']}/oauth2/v2.0/token";

    $postFields = http_build_query([
        "client_id" => $cfg["client_id"],
        "client_secret" => $cfg["client_secret"],
        "scope" => "https://graph.microsoft.com/.default",
        "grant_type" => "client_credentials",
    ]);

    $ch = curl_init($tokenUrl);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $postFields,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ["Content-Type: application/x-www-form-urlencoded"],
        CURLOPT_TIMEOUT => 20,
    ]);

    $resp = curl_exec($ch);
    $err  = curl_error($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($resp === false) {
        throw new Exception("Error cURL token: $err");
    }

    $json = json_decode($resp, true);
    if ($code < 200 || $code >= 300) {
        $msg = $json["error_description"] ?? $resp;
        throw new Exception("Token HTTP $code: $msg");
    }

    if (!isset($json["access_token"])) {
        throw new Exception("No llegó access_token: " . $resp);
    }

    return $json["access_token"];
}

/**
 * Enviar correo por Microsoft Graph (HTML + adjuntos opcionales)
 * $attachments: array de rutas locales (strings)
 */
function graph_send_mail(array $cfg, string $toEmail, string $toName, string $subject, string $htmlBody, array $attachments = []): void {
    $token = graph_get_token($cfg);

    $sender = rawurlencode($cfg["sender"]);
    $url = "https://graph.microsoft.com/v1.0/users/{$sender}/sendMail";

    $message = [
        "subject" => $subject,
        "body" => [
            "contentType" => "HTML",
            "content" => $htmlBody,
        ],
        "toRecipients" => [
            ["emailAddress" => ["address" => $toEmail, "name" => $toName]]
        ],
    ];

    // Adjuntos (máximo práctico: ~3MB por request; para grandes se hace upload session)
    if (!empty($attachments)) {
        $attArr = [];
        foreach ($attachments as $path) {
            if (!$path || !file_exists($path)) continue;

            $bytes = file_get_contents($path);
            if ($bytes === false) continue;

            $filename = basename($path);
            $mime = mime_content_type($path) ?: "application/octet-stream";

            // OJO: si el archivo es muy grande, Graph puede fallar por tamaño
            $attArr[] = [
                "@odata.type" => "#microsoft.graph.fileAttachment",
                "name" => $filename,
                "contentType" => $mime,
                "contentBytes" => base64_encode($bytes),
            ];
        }

        if (!empty($attArr)) {
            $message["attachments"] = $attArr;
        }
    }

    $payload = [
        "message" => $message,
        "saveToSentItems" => true
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer {$token}",
            "Content-Type: application/json"
        ],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_TIMEOUT => 25,
    ]);

    $resp = curl_exec($ch);
    $err  = curl_error($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($resp === false) {
        throw new Exception("Error cURL sendMail: $err");
    }

    // Graph responde 202 Accepted
    if ($code !== 202) {
        $j = json_decode($resp, true);
        $msg = $j["error"]["message"] ?? $resp;
        throw new Exception("sendMail HTTP $code: $msg");
    }
}

/**
 * FUNCIÓN COMPATIBLE con tu ERP (misma firma que venías usando)
 */
function enviarCorreoFundocol($para, $paraNombre, $asunto, $htmlCuerpo, $adjuntos = []) {
    $cfg = require __DIR__ . "/../config/graph_mail.php";

    // ============================
    // MODO LOCAL / STAGING (SEGURIDAD)
    // ============================
    $esLocal = fundocol_is_local_env();
    if ($esLocal) {
        $para = $cfg["force_local_to"] ?? "sistemas@fundocol.org";
        $paraNombre = "Jhoan (LOCAL)";
        $asunto = "[LOCAL] " . $asunto;
    }

    try {
        graph_send_mail($cfg, $para, $paraNombre, $asunto, $htmlCuerpo, is_array($adjuntos) ? $adjuntos : []);
        return true;
    } catch (Throwable $e) {
        // En local puedes mostrar el error si quieres
        if ($esLocal) {
            echo "<pre>Error correo (Graph): " . htmlspecialchars($e->getMessage()) . "</pre>";
        }
        return false;
    }
}
