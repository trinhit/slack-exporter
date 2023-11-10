<?php

require_once 'config.php';

echo <<<PULLING_USERS
================================================
                  PULLING USERS
================================================

PULLING_USERS;

// Create table
R::exec("
CREATE TABLE IF NOT EXISTS `users` (
     `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
     `identifier` varchar(191) DEFAULT NULL,
     `name` varchar(191) DEFAULT NULL,
     `deleted` tinyint(1) unsigned DEFAULT NULL,
     `real_name` varchar(191) DEFAULT NULL,
     `title` varchar(191) DEFAULT NULL,
     `phone` varchar(191) DEFAULT NULL,
     `real_name_normalized` varchar(191) DEFAULT NULL,
     `display_name` varchar(191) DEFAULT NULL,
     `display_name_normalized` varchar(191) DEFAULT NULL,
     `image_original` varchar(191) DEFAULT NULL,
     `is_custom_image` tinyint(1) unsigned DEFAULT NULL,
     `is_admin` tinyint(1) unsigned DEFAULT NULL,
     `is_owner` tinyint(1) unsigned DEFAULT NULL,
     `is_primary_owner` tinyint(1) unsigned DEFAULT NULL,
     `is_restricted` tinyint(1) unsigned DEFAULT NULL,
     `is_ultra_restricted` tinyint(1) unsigned DEFAULT NULL,
     `is_bot` tinyint(1) unsigned DEFAULT NULL,
     `is_app_user` tinyint(1) unsigned DEFAULT NULL,
     `updated` int(11) unsigned DEFAULT NULL,
     `avatar_local_path` varchar(191) DEFAULT NULL,
     PRIMARY KEY (`id`),
     UNIQUE KEY `identifier` (`identifier`),
     KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Set some API parameters
$limit = 100;
$cursor = '';
$params = [
    'cursor' => $cursor,
    'limit' => $limit,
];

// Set some progress tracking variables
$loop = 0;
$numProcessedUsers = 0;

// Begin pulling users
do {
    $loop++;

    // Send request to Slack API and get back data
    $response = sendPostRequestToSlackApi('users.list', $params);
    $data = json_decode($response, true);

    // Process fetched users
    foreach ($data['members'] as $user) {
        echo 'Processing user `' . $user['name'] . '` (' . $user['id'] . ")\n";

        // Check for existence
        $usersModel = R::findOneForUpdate('users', '`identifier` = :identifier', [':identifier' => $user['id']]);
        if (empty($usersModel)) {
            $usersModel = R::dispense('users');
            echo "User does not exist on DB. Inserting a new one...";
        }
        else {
            echo "User exists on DB. Updating existing record...";
        }
        echo "\n";

        // Store user properties
        $usersModel->identifier = $user['id'] ?? null;
        $usersModel->name = $user['name'] ?? null;
        $usersModel->deleted = $user['deleted'] ?? null;
        $usersModel->real_name = $user['real_name'] ?? null;
        $usersModel->title = $user['profile']['title'] ?? null;
        $usersModel->phone = $user['profile']['phone'] ?? null;
        $usersModel->real_name = $user['profile']['real_name'] ?? null;
        $usersModel->real_name_normalized = $user['profile']['real_name_normalized'] ?? null;
        $usersModel->display_name = $user['profile']['display_name'] ?? null;
        $usersModel->display_name_normalized = $user['profile']['display_name_normalized'] ?? null;
        $usersModel->image_original = $user['profile']['image_original'] ?? null;
        $usersModel->is_custom_image = $user['profile']['is_custom_image'] ?? null;
        $usersModel->is_admin = $user['is_admin'] ?? null;
        $usersModel->is_owner = $user['is_owner'] ?? null;
        $usersModel->is_primary_owner = $user['is_primary_owner'] ?? null;
        $usersModel->is_restricted = $user['is_restricted'] ?? null;
        $usersModel->is_ultra_restricted = $user['is_ultra_restricted'] ?? null;
        $usersModel->is_bot = $user['is_bot'] ?? null;
        $usersModel->is_app_user = $user['is_app_user'] ?? null;
        $usersModel->updated = $user['updated'] ?? null;

        // Download avatar
        $avatarUrl = $user['profile']['image_original'] ?? null;
        if (validUrl($avatarUrl)) {
            $targetPath = 'avatars' . DIRECTORY_SEPARATOR . $user['id'] . '-' . basename($avatarUrl);
            if (file_exists($targetPath) || downloadSlackFile($avatarUrl, $targetPath)) {
                $usersModel->avatar_local_path = $targetPath;
                echo "Downloaded user avatar\n";
            }
        }

        R::store($usersModel);

        $numProcessedUsers++;

        echo "Processed $numProcessedUsers user(s)\n\n";
    }

    // Next page?
    $nextCursor = $data['response_metadata']['next_cursor'];
    $params['cursor'] = $nextCursor;

    echo "\n";
}
while( strlen($nextCursor) > 0 /*&& $loop < 3*/ );

echo "\n";
