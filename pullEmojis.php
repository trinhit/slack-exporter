<?php

require_once 'config.php';

echo <<<PULLING_EMOJIS
================================================
                PULLING EMOJIS
================================================

PULLING_EMOJIS;

// Create table
R::exec("
CREATE TABLE IF NOT EXISTS `emojis` (
     `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
     `name` varchar(191) DEFAULT NULL,
     `url` varchar(191) DEFAULT NULL,
     `category` varchar(191) DEFAULT NULL,
     PRIMARY KEY (`id`),
     UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Set some API parameters
$params = [
    'include_categories' => 'true',
];

// Send request to Slack API and get back data
$response = sendPostRequestToSlackApi('emoji.list', $params);
$data = json_decode($response, true);
$emojiCount = count($data['emoji']);

if ($emojiCount > 0) {
    echo "Processing emojis...\n";

    foreach ($data['emoji'] as $emojiName => $emojiUrl) {
        $emojisModel = R::findOneForUpdate('emojis', '`name` = :name', [':name' => $emojiName]);
        if (empty($emojisModel)) {
            $emojisModel = R::dispense('emojis');
        }

        $emojisModel->name = $emojiName;
        $emojisModel->url = $emojiUrl;

        // Download the custom emoji
        if (validUrl($emojiUrl)) {
            $targetPath = 'emojis' . DIRECTORY_SEPARATOR . basename($emojiUrl);
            if (!file_exists($targetPath) && downloadSlackFile($emojiUrl, $targetPath)) {
                $emojisModel->file_local_path = $targetPath;
            }
        }

        R::store($emojisModel);
    }

    echo "Stored $emojiCount emojis\n";
}

$categoryCount = count($data['categories']);
if ($categoryCount > 0) {
    echo "Processing emoji categories...\n";

    foreach ($data['categories'] as $category) {
        $categoryName = $category['name'];
        foreach ($category['emoji_names'] as $emojiName) {
            $emojisModel = R::findOneForUpdate('emojis', '`name` = :name', [':name' => $emojiName]);
            if (empty($emojisModel)) {
                $emojisModel = R::dispense('emojis');
            }

            $emojisModel->name = $emojiName;
            $emojisModel->category = $categoryName;

            R::store($emojisModel);
        }
    }

    echo "Stored $categoryCount emoji categories\n";
}
