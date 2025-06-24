<?php

namespace ReallySpecific\BetterLLMStxt\Dependencies\RS_Utils\Network;

use WP_Post;
/**
 * Sends a network request to Cloudflare to get the public facing IP of this server
 *
 * @param string $slug
 * @return WP_Post|null
 *
 * @throws \Exception
 */
function get_server_remote_ip()
{
    $response = file_get_contents('https://www.cloudflare.com/cdn-cgi/trace');
    if (\false === $response) {
        return \false;
    }
    $parts = explode("\n", $response);
    if (empty($parts)) {
        return \false;
    }
    $parts = array_map('trim', $parts);
    foreach ($parts as $part) {
        if (strpos($part, 'ip=') === 0) {
            $address = substr($part, 3);
            return $address;
        }
    }
    return \false;
}
