<?php
// upload_opportunity_image.php
// Handles image upload for opportunities

//$targetDir = 'https://kapp.khulumaeswatini.com/uploads/';
$targetDir = $_SERVER['DOCUMENT_ROOT'] . 'public_html/client/build/web/uploads/';
if (!file_exists($targetDir)) {
    mkdir($targetDir, 0777, true);
}

$response = ["success" => false];

if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
    $fileTmpPath = $_FILES['image']['tmp_name'];
    $fileName = basename($_FILES['image']['name']);
    $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    $allowedExts = ['jpg', 'jpeg', 'png', 'gif'];

    if (in_array($fileExt, $allowedExts)) {
        $newFileName = uniqid('opp_', true) . '.' . $fileExt;
        $destPath = $targetDir . $newFileName;
        if (move_uploaded_file($fileTmpPath, $destPath)) {
            $url = 'https://kapp.khulumaeswatini.com/uploads/' . $newFileName;
            $response = ["success" => true, "url" => $url];
        } else {
            $response["error"] = "Failed to move uploaded file.";
        }
    } else {
        $response["error"] = "Invalid file type.";
    }
} else {
    $response["error"] = "No file uploaded or upload error.";
}

header('Content-Type: application/json');
echo json_encode($response);
