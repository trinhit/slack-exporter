<?php

require_once 'init.php';

echo <<<PULLING_CONVERSATION_BOOKMARKS
================================================
         PULLING CONVERSATION BOOKMARKS
================================================

PULLING_CONVERSATION_BOOKMARKS;

$sleepTime = 1;

// Create table
R::exec("
CREATE TABLE IF NOT EXISTS `conversationbookmarks` ( # because RedBeanPHP doesn't support underscores :(
     `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
     `identifier` varchar(191) DEFAULT NULL,
     `conversation_identifier` varchar(191) DEFAULT NULL,
     `title` varchar(191) DEFAULT NULL,
     `link` varchar(191) DEFAULT NULL,
     `emoji` tinyint(1) unsigned DEFAULT NULL,
     `icon_url` varchar(191) DEFAULT NULL,
     `type` varchar(191) DEFAULT NULL,
     `date_created` int(11) unsigned DEFAULT NULL,
     `date_updated` int(11) unsigned DEFAULT NULL,
     `rank` varchar(191) DEFAULT NULL,
     PRIMARY KEY (`id`),
     UNIQUE KEY `identifier` (`identifier`),
     KEY `conversation_identifier` (`conversation_identifier`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$numProcessedConversations = 0;

// Find existing conversations on database
$conversations = R::findAll('conversations');
$conversationCount = count($conversations);

// Find members of each conversation
foreach ($conversations as $conversation) {
    echo "Pulling bookmarks of conversation `$conversation[name]` ($conversation[identifier])...\n";

    // Set some API parameters
    $params = [
        'channel_id' => $conversation['identifier'],
    ];

    // Send request to Slack API and get back data
    $response = sendPostRequestToSlackApi('bookmarks.list', $params);
    $data = json_decode($response, true);
    $bookmarkCount = isset($data['bookmarks']) ? count($data['bookmarks']) : 0;

    if ($bookmarkCount > 0) {
        // Delete existing records
        R::exec('DELETE FROM `conversationbookmarks` WHERE `conversation_identifier` = :conversation_identifier', [':conversation_identifier' => $conversation['identifier']]);

        // Store bookmarks into database
        foreach ($data['bookmarks'] as $bookmark) {
            $bookmarkModel = R::dispense('conversationbookmarks');
            $bookmarkModel->identifier = $bookmark['id'] ?? null;
            $bookmarkModel->conversation_identifier = $conversation['identifier'] ?? null;
            $bookmarkModel->title = $bookmark['title'] ?? null;
            $bookmarkModel->link = $bookmark['link'] ?? null;
            $bookmarkModel->emoji = $bookmark['emoji'] ?? null;
            $bookmarkModel->icon_url = $bookmark['icon_url'] ?? null;
            $bookmarkModel->type = $bookmark['type'] ?? null;
            $bookmarkModel->date_created = $bookmark['date_created'] ?? null;
            $bookmarkModel->date_updated = $bookmark['date_updated'] ?? null;
            $bookmarkModel->rank = $bookmark['rank'] ?? null;

            R::store($bookmarkModel);
        }

        echo "Stored $bookmarkCount bookmarks(s)\n";
    }
    else {
        echo "There is no bookmark\n";
    }

    $numProcessedConversations++;

    echo "Processed $numProcessedConversations of $conversationCount conversation(s)\n\n";

    echo "Sleeping for $sleepTime second(s)...\n";
    sleep($sleepTime);
}
