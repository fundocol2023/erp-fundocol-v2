<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php';

function enviarCorreoFundocol($para, $paraNombre, $asunto, $htmlCuerpo, $adjuntos = []) {
    $mail = new PHPMailer(true);
    try {
        // Configuración SMTP Outlook
        $mail->isSMTP();
        $mail->Host = 'smtp.office365.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'notificaciones@fundocol.org';
        $mail->Password = 'Rsat8700';
        $mail->SMTPSecure = 'tls';
        $mail->Port = 587;

        $mail->setFrom('notificaciones@fundocol.org', 'Fundocol ERP');
        $mail->addAddress($para, $paraNombre);

        if (!empty($adjuntos) && is_array($adjuntos)) {
            foreach ($adjuntos as $adj) {
                if (file_exists($adj)) {
                    $mail->addAttachment($adj);
                }
            }
        }

        $mail->isHTML(true);
        $mail->Subject = $asunto;
        $mail->Body = $htmlCuerpo;

        $mail->send();
        return true;
    } catch (Exception $e) {
        return false;
    }
}
?>
