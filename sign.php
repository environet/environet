<?php

$dir = '/Users/catchke2ro/sites/_dareffort/dareffort_docker';
chdir($dir);

if (!(($parsedUrl = parse_url($_SERVER['REQUEST_URI'] ?? null)) && ($path = $parsedUrl['path'] ?? null) && ($subject = trim($path, '/')))) {
	http_send_status(400);
	exit;
}

echo exec('./environet dist tool sign ' . $subject);