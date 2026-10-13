<?php

/**
 * Minimal fake Redis server for reconnect tests.
 *
 * The reply to EVAL (a two-element multi-bulk) is sent in two parts with a pause between them,
 * so a client with a short read_timeout gives up in the middle of it while the rest of the reply
 * is still on its way. argv[2] picks where the reply is cut: between the elements or inside the
 * length line of the second one. (A cut inside a payload makes phpredis close the socket itself.)
 * GET <key> replies with <key>, everything else with +OK.
 *
 * Clients are served one after another. Prints the listening port on the first line of
 * STDOUT and appends "accept" / "<COMMAND>" lines to the log file passed as argv[1].
 */
$log = $argv[1];
[$head, $tail] = match ($argv[2] ?? 'element') {
    'element' => ["*2\r\n\$5\r\nfirst\r\n", "\$4\r\ntail\r\n"],
    'length' => ["*2\r\n\$5\r\nfirst\r\n\$", "4\r\ntail\r\n"],
    default => throw new InvalidArgumentException("Unknown cut point: {$argv[2]}"),
};

$server = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
if ($server === false) {
    fwrite(STDERR, "$errstr\n");
    exit(1);
}

echo parse_url('tcp://' . stream_socket_get_name($server, false), PHP_URL_PORT), PHP_EOL;
fflush(STDOUT);

$bulk = static fn (string $value): string => '$' . strlen($value) . "\r\n" . $value . "\r\n";

while ($client = @stream_socket_accept($server, 5)) {
    file_put_contents($log, "accept\n", FILE_APPEND);

    while (($header = fgets($client)) !== false) {
        if ($header[0] !== '*') {
            continue;
        }

        $args = [];
        for ($i = (int) substr($header, 1); $i > 0; $i--) {
            $length = (int) substr(fgets($client), 1);
            $args[] = $length > 0 ? stream_get_contents($client, $length) : '';
            fgets($client);
        }

        $command = strtoupper($args[0]);
        file_put_contents($log, $command . "\n", FILE_APPEND);

        if ($command === 'EVAL') {
            fwrite($client, $head);
            usleep(400000);
            @fwrite($client, $tail);
        } elseif ($command === 'GET') {
            fwrite($client, $bulk($args[1]));
        } else {
            fwrite($client, "+OK\r\n");
        }
    }

    fclose($client);
}
