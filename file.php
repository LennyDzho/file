<?php

function uploadFileToCollection($collectionId) {
    file_put_contents('file_upload.log', 'Starting file upload' . PHP_EOL, FILE_APPEND);
    $tokens = executeCurlRequest();

    if ($tokens === false || !isset($tokens['bearer'], $tokens['cookie'], $tokens['xsrf'])) {
        file_put_contents('file_upload.log', 'Failed to retrieve required tokens.' . PHP_EOL, FILE_APPEND);
        echo json_encode(['status' => 'Failed', 'message' => 'Failed to retrieve required tokens.']);
        return;
    }

    if (!isset($_FILES['file']) || $_FILES['file']['error'] != UPLOAD_ERR_OK) {
        file_put_contents('file_upload.log', 'File upload error: ' . $_FILES['file']['error'] . PHP_EOL, FILE_APPEND);
        echo json_encode(['status' => 'Failed', 'message' => 'File upload error.']);
        return;
    }

    $filePath = $_FILES['file']['tmp_name'];
    $fileName = $_FILES['file']['name'];

    // Step 1: Create Item
    file_put_contents('file_upload.log', 'Creating item in collection: ' . $collectionId . PHP_EOL, FILE_APPEND);
    $url = 'https://dspace7-test.uni-dubna.ru/server/api/core/items?owningCollection=' . $collectionId;
    $data = json_encode([
        'name' => $fileName,
        'metadata' => [
            'dc.title' => [
                ['value' => $fileName, 'language' => null]
            ],
            'dc.contributor.author' => [
                [
                    'value' => 'Author Name',
                    'language' => 'en',
                    'authority' => null,
                    'confidence' => -1
                ]
            ],
            'dc.type' => [
                [
                    'value' => 'Image',
                    'language' => 'en',
                    'authority' => null,
                    'confidence' => -1
                ]
            ]
        ],
        'inArchive' => true,
        'discoverable' => true,
        'withdrawn' => false,
        'type' => 'item'
    ]);
    $headers = [
        'Authorization: Bearer ' . $tokens['bearer'],
        'Content-Type: application/json',
        'X-XSRF-TOKEN: ' . $tokens['xsrf']
    ];
    $cookie = 'DSPACE-XSRF-COOKIE=' . $tokens['cookie'];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_COOKIE, $cookie);
    curl_setopt($ch, CURLOPT_VERBOSE, true);

    $verbose = fopen('php://temp', 'rw+');
    curl_setopt($ch, CURLOPT_STDERR, $verbose);

    $response = curl_exec($ch);

    if ($response === false) {
        $error = curl_error($ch);
        file_put_contents('file_upload.log', 'CURL error (create item): ' . $error . PHP_EOL, FILE_APPEND);
        curl_close($ch);
        fclose($verbose);
        echo json_encode(['status' => 'Failed', 'message' => 'CURL error: ' . $error]);
        return;
    }

    rewind($verbose);
    $verboseLog = stream_get_contents($verbose);
    file_put_contents('file_upload.log', 'Create Item Request Data: ' . $data . PHP_EOL . $verboseLog . PHP_EOL, FILE_APPEND);

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $responseData = json_decode($response, true);
    file_put_contents('file_upload.log', 'Create Item HTTP Code: ' . $httpCode . PHP_EOL . 'Response Body: ' . $response . PHP_EOL, FILE_APPEND);

    curl_close($ch);
    fclose($verbose);

    if ($httpCode != 201 || !isset($responseData['id'])) {
        file_put_contents('file_upload.log', 'Failed to create item. HTTP Code: ' . $httpCode . PHP_EOL, FILE_APPEND);
        echo json_encode(['status' => 'Failed', 'message' => 'Failed to create item.']);
        return;
    }

    $itemId = $responseData['uuid'];

    // Step 2: Create Bundle
    file_put_contents('file_upload.log', 'Creating bundle in item: ' . $itemId . PHP_EOL, FILE_APPEND);
    $url = 'https://dspace7-test.uni-dubna.ru/server/api/core/items/' . $itemId . '/bundles';
    $data = json_encode([
        'name' => 'ORIGINAL',
        'metadata' => new stdClass()
    ]);
    $headers = [
        'Authorization: Bearer ' . $tokens['bearer'],
        'Content-Type: application/json',
        'X-XSRF-TOKEN: ' . $tokens['xsrf']
    ];
    $cookie = 'DSPACE-XSRF-COOKIE=' . $tokens['cookie'];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_COOKIE, $cookie);
    curl_setopt($ch, CURLOPT_VERBOSE, true);

    $verbose = fopen('php://temp', 'rw+');
    curl_setopt($ch, CURLOPT_STDERR, $verbose);

    $response = curl_exec($ch);

    if ($response === false) {
        $error = curl_error($ch);
        curl_close($ch);
        fclose($verbose);
        file_put_contents('file_upload.log', 'CURL error (create bundle): ' . $error . PHP_EOL, FILE_APPEND);
        echo json_encode(['status' => 'Failed', 'message' => 'CURL error: ' . $error]);
        return;
    }

    rewind($verbose);
    $verboseLog = stream_get_contents($verbose);
    file_put_contents('file_upload.log', 'Create Bundle Request Data: ' . $data . PHP_EOL . $verboseLog . PHP_EOL, FILE_APPEND);

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $responseData = json_decode($response, true);
    file_put_contents('file_upload.log', 'Create Bundle HTTP Code: ' . $httpCode . PHP_EOL . 'Response Body: ' . $response . PHP_EOL, FILE_APPEND);

    curl_close($ch);
    fclose($verbose);



    $bundleId = $responseData['uuid'];

    // Step 3: Upload Bitstream
    file_put_contents('file_upload.log', 'Uploading bitstream to bundle: ' . $bundleId . PHP_EOL, FILE_APPEND);
    $url = 'https://dspace7-test.uni-dubna.ru/server/api/core/bundles/' . $bundleId . '/bitstreams';
    $data = [
        'file' => new CURLFile($filePath, mime_content_type($filePath), $fileName)
    ];
    $headers = [
        'Authorization: Bearer ' . $tokens['bearer'],
        'X-XSRF-TOKEN: ' . $tokens['xsrf']
    ];
    $cookie = 'DSPACE-XSRF-COOKIE=' . $tokens['cookie'];

    // Check if bundleId is set
    if (empty($bundleId)) {
        file_put_contents('file_upload.log', 'Bundle ID is empty. Cannot upload bitstream.' . PHP_EOL, FILE_APPEND);
        echo json_encode(['status' => 'Failed', 'message' => 'Bundle ID is empty.']);
        return;
    }

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_COOKIE, $cookie);
    curl_setopt($ch, CURLOPT_VERBOSE, true);

    $verbose = fopen('php://temp', 'rw+');
    curl_setopt($ch, CURLOPT_STDERR, $verbose);

    $response = curl_exec($ch);

    if ($response === false) {
        $error = curl_error($ch);
        curl_close($ch);
        fclose($verbose);
        file_put_contents('file_upload.log', 'CURL error (upload bitstream): ' . $error . PHP_EOL, FILE_APPEND);
        echo json_encode(['status' => 'Failed', 'message' => 'CURL error: ' . $error]);
        return;
    }

    rewind($verbose);
    $verboseLog = stream_get_contents($verbose);
    file_put_contents('file_upload.log', 'Upload Bitstream Request Data: ' . json_encode($data) . PHP_EOL . $verboseLog . PHP_EOL, FILE_APPEND);

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    file_put_contents('file_upload.log', 'Upload Bitstream HTTP Code: ' . $httpCode . PHP_EOL . 'Response Body: ' . $response . PHP_EOL, FILE_APPEND);

    curl_close($ch);
    fclose($verbose);

    echo json_encode(['status' => 'Success', 'uploadResponse' => $response]);
}
?>
