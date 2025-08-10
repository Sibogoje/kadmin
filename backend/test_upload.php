<?php
// Test script to debug the documents upload API
echo "Testing document upload API...\n";

// First test - simple GET request to see if the API is accessible
echo "1. Testing basic API access...\n";
$url = 'http://localhost/kapp/backend/api/admin/documents.php';
$context = stream_context_create([
    'http' => [
        'method' => 'GET',
        'header' => "Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpZCI6MSwiZW1haWwiOiJhZG1pbkBraHVsdW1hLmNvbSIsInVzZXJuYW1lIjoic3VwZXJhZG1pbiIsInJvbGUiOiJzdXBlcl9hZG1pbiIsImV4cCI6MTc1NDI5NjA2MH0.IBG96M_Am54WRfBat8g5aNFN2JuOIpEXDCEtNsVv-Ks\r\n"
    ]
]);

$result = file_get_contents($url, false, $context);
if ($result !== false) {
    echo "GET request successful: " . $result . "\n";
} else {
    echo "GET request failed\n";
    $error = error_get_last();
    echo "Error: " . ($error['message'] ?? 'Unknown error') . "\n";
}

echo "\n2. Testing POST upload endpoint accessibility...\n";
$uploadUrl = 'https://khulumaeswaini.com/app/backend/api/admin/documents.php?action=upload';

// Create a simple test file
$testFileContent = "This is a test document for upload testing.";
$tempFile = tempnam(sys_get_temp_dir(), 'test_doc');
file_put_contents($tempFile, $testFileContent);

// Prepare multipart data
$boundary = '----WebKitFormBoundary' . uniqid();
$postData = '';

// Add client_id field
$postData .= "--$boundary\r\n";
$postData .= "Content-Disposition: form-data; name=\"client_id\"\r\n\r\n";
$postData .= "1\r\n";

// Add document_name field
$postData .= "--$boundary\r\n";
$postData .= "Content-Disposition: form-data; name=\"document_name\"\r\n\r\n";
$postData .= "test_document.txt\r\n";

// Add document_type field
$postData .= "--$boundary\r\n";
$postData .= "Content-Disposition: form-data; name=\"document_type\"\r\n\r\n";
$postData .= "other\r\n";

// Add file
$postData .= "--$boundary\r\n";
$postData .= "Content-Disposition: form-data; name=\"document\"; filename=\"test_document.txt\"\r\n";
$postData .= "Content-Type: text/plain\r\n\r\n";
$postData .= $testFileContent . "\r\n";
$postData .= "--$boundary--\r\n";

$context = stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => "Content-Type: multipart/form-data; boundary=$boundary\r\n" .
                   "Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpZCI6MSwiZW1haWwiOiJhZG1pbkBraHVsdW1hLmNvbSIsInVzZXJuYW1lIjoic3VwZXJhZG1pbiIsInJvbGUiOiJzdXBlcl9hZG1pbiIsImV4cCI6MTc1NDI5NjA2MH0.IBG96M_Am54WRfBat8g5aNFN2JuOIpEXDCEtNsVv-Ks\r\n",
        'content' => $postData
    ]
]);

echo "Sending POST request to: $uploadUrl\n";
$result = file_get_contents($uploadUrl, false, $context);

if ($result !== false) {
    echo "POST request successful: " . $result . "\n";
} else {
    echo "POST request failed\n";
    $error = error_get_last();
    echo "Error: " . ($error['message'] ?? 'Unknown error') . "\n";
    
    // Check HTTP response headers
    if (isset($http_response_header)) {
        echo "Response headers:\n";
        foreach ($http_response_header as $header) {
            echo "  $header\n";
        }
    }
}

// Clean up
unlink($tempFile);
echo "\nTest completed.\n";
?>
