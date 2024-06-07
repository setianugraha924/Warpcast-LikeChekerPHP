<?php

// CODING BY SETIA NUGRAHA
// Function to read authentication information
function read_auth_and_username() {
    try {
        $file = fopen('akun.txt', 'r');
        $content = fgets($file);
        $tokenBarier = rtrim($content, "\n");
        $content = fgets($file);
        $myUname = rtrim($content, "\n");
        fclose($file);

        return array($tokenBarier, $myUname);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(array("error" => "Terjadi pengecualian: $e"));
        exit();
    }
}

// Function to set up HTTP request
function setupRequest($METHOD, $URL, $HEADERS, $PAYLOAD = null) {
    try {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $URL);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $METHOD);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $HEADERS);
        if ($PAYLOAD) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $PAYLOAD);
        }
        $response = curl_exec($ch);
        if (!$response) {
            throw new Exception(curl_error($ch));
        }
        curl_close($ch);
        return json_decode($response, true);
    } catch (Exception $e) {
        error_log("Curl error: " . $e->getMessage());
        return null;
    }
}

// Function to check like status for a single URL
function checkLikeStatus($url, $tokenBarier, $myUname) {
    preg_match('/(https?:\/\/\S+)/', $url, $matches);
    if (!empty($matches)) {
        $url = $matches[0];
    } else {
        return array("result" => "Invalid URL: $url");
    }

    $parsed_url = parse_url($url);
    if (!isset($parsed_url['scheme']) || !isset($parsed_url['host'])) {
        return array("result" => "Invalid URL: $url");
    }

    $path_parts = explode("/", $parsed_url['path']);
    if (count($path_parts) < 3) {
        return array("result" => "Invalid URL format: $url");
    }

    $username = $path_parts[1];
    $identifier = $path_parts[2];

    $getHash = setupRequest("GET", "https://client.warpcast.com/v2/user-thread-casts?castHashPrefix=$identifier&username=$username&limit=15", array(
        'authority: client.warpcast.com',
        'accept: */*',
        'accept-language: id-ID,id;q=0.9,en-US;q=0.8,en;q=0.7',
        'content-type: application/json; charset=utf-8',
        'origin: https://warpcast.com',
        'referer: https://warpcast.com/',
        'user-agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/121.0.0.0 Safari/537.36',
        'authorization: Bearer ' . $tokenBarier
    ));

    if ($getHash === null) {
        return array("result" => "Failed to fetch data for: $url");
    }

    if (!isset($getHash['result']) || !isset($getHash['result']['casts'])) {
        return array("result" => "Invalid response structure for: $url", "response" => $getHash);
    }

    foreach ($getHash['result']['casts'] as $cast) {
        $hash = $cast['hash'];
        if (strpos($hash, $identifier) === false) {
            continue;
        }

        $likedUsers = fetchLikers($hash, $tokenBarier);
        if (in_array($myUname, $likedUsers)) {
            return array("result" => "SUKSES ONYO: $url");
        }
    }

    return array("result" => "Not liked yet: $url");
}

// Function to fetch likers of a specific cast
function fetchLikers($hash, $tokenBarier) {
    $likers = [];
    $cursor = "";
    $isMoreThan100 = true;

    while ($isMoreThan100) {
        $url = "https://client.warpcast.com/v2/cast-likes?castHash=$hash&limit=100";
        if ($cursor) {
            $url .= "&cursor=$cursor";
        }

        $response = setupRequest("GET", $url, array(
            'authority: client.warpcast.com',
            'accept: */*',
            'accept-language: id-ID,id;q=0.9,en-US;q=0.8,en;q=0.7',
            'content-type: application/json; charset=utf-8',
            'origin: https://warpcast.com',
            'referer: https://warpcast.com/',
            'user-agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/121.0.0.0 Safari/537.36',
            'authorization: Bearer ' . $tokenBarier
        ));

        if ($response === null || !isset($response['result']) || !isset($response['result']['likes'])) {
            break;
        }

        foreach ($response['result']['likes'] as $like) {
            $likers[] = $like['reactor']['username'];
        }

        if (isset($response['next']) && isset($response['next']['cursor'])) {
            $cursor = $response['next']['cursor'];
        } else {
            $isMoreThan100 = false;
        }
    }

    return $likers;
}

// Main function to run the script
function main() {
    if (!isset($_GET['url'])) {
        http_response_code(400);
        echo json_encode(array("error" => "URL parameter missing"));
        return;
    }

    $url = $_GET['url'];
    list($tokenBarier, $myUname) = read_auth_and_username();
    $result = checkLikeStatus($url, $tokenBarier, $myUname);

    http_response_code(200);
    header('Content-Type: application/json');
    echo json_encode($result);
}

// Execute main function
main();
?>
