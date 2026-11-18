<?php

/**
 * Servidor SMTP de mentira para las pruebas: atiende UNA conversación y guarda todo lo que recibió.
 * Uso: php servidor-smtp-falso.php <puerto> <archivo-de-salida> [rechazar]
 * Con "rechazar", responde 550 al destinatario (para probar que el cliente informa el error).
 */

declare(strict_types=1);

[, $puerto, $salida] = $argv;
$rechazar = ($argv[3] ?? '') === 'rechazar';
$servidor = stream_socket_server('tcp://127.0.0.1:' . $puerto, $numero, $error);
if ($servidor === false) {
    fwrite(STDERR, $error);
    exit(1);
}
file_put_contents($salida . '.listo', '1');
$cliente = stream_socket_accept($servidor, 20);
if ($cliente === false) {
    exit(1);
}
$registro = '';
$responder = function (string $texto) use ($cliente, &$registro): void {
    fwrite($cliente, $texto . "\r\n");
    $registro .= 'S: ' . $texto . "\n";
};
$responder('220 falso.test ESMTP listo');
$enDatos = false;
$autenticando = 0;
while (($linea = fgets($cliente)) !== false) {
    $registro .= 'C: ' . rtrim($linea, "\r\n") . "\n";
    $comando = strtoupper(substr(trim($linea), 0, 4));
    if ($enDatos) {
        if (rtrim($linea, "\r\n") === '.') {
            $enDatos = false;
            $responder('250 recibido');
        }
        continue;
    }
    if ($autenticando > 0) {
        $autenticando--;
        $responder($autenticando > 0 ? '334 UGFzc3dvcmQ6' : '235 autenticado');
        continue;
    }
    match (true) {
        $comando === 'EHLO' => $responder("250-falso.test\r\n250 AUTH LOGIN"),
        str_starts_with(strtoupper(trim($linea)), 'AUTH LOGIN') => (function () use ($responder, &$autenticando) {
            $autenticando = 2;
            $responder('334 VXNlcm5hbWU6');
        })(),
        $comando === 'MAIL' => $responder('250 ok'),
        $comando === 'RCPT' => $responder($rechazar ? '550 no existe ese buzón' : '250 ok'),
        $comando === 'DATA' => (function () use ($responder, &$enDatos) {
            $enDatos = true;
            $responder('354 adelante');
        })(),
        $comando === 'QUIT' => $responder('221 chau'),
        default => $responder('502 no entiendo'),
    };
    if ($comando === 'QUIT' || ($rechazar && $comando === 'RCPT')) {
        break;
    }
}
// Primero a un archivo aparte y después se renombra: así la prueba nunca lee el archivo a medio escribir
file_put_contents($salida . '.tmp', $registro);
rename($salida . '.tmp', $salida);
fclose($cliente);
