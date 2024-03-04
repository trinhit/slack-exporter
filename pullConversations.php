<?php

/**
 * Background: Slack uses the term 'Conversation' for channels (public/private), group chats, and direct chats
 * This script pulls all the conversations that the currently authorized user can access
 */

require_once 'init.php';

echo <<<PULLING_CONVERSATIONS
================================================
             PULLING CONVERSATIONS
================================================

PULLING_CONVERSATIONS;

$sleepTime = 3;

// Create table
R::exec("
CREATE TABLE IF NOT EXISTS `conversations` (
     `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
     `identifier` varchar(191) DEFAULT NULL,
     `name` varchar(191) DEFAULT NULL,
     `is_channel` tinyint(1) unsigned DEFAULT NULL,
     `is_group` tinyint(1) unsigned DEFAULT NULL,
     `is_im` tinyint(1) unsigned DEFAULT NULL,
     `is_mpim` tinyint(1) unsigned DEFAULT NULL,
     `is_private` tinyint(1) unsigned DEFAULT NULL,
     `created` int(11) unsigned DEFAULT NULL,
     `is_archived` tinyint(1) unsigned DEFAULT NULL,
     `is_general` tinyint(1) unsigned DEFAULT NULL,
     `unlinked` tinyint(1) unsigned DEFAULT NULL,
     `name_normalized` varchar(191) DEFAULT NULL,
     `is_shared` tinyint(1) unsigned DEFAULT NULL,
     `is_org_shared` tinyint(1) unsigned DEFAULT NULL,
     `is_pending_ext_shared` tinyint(1) unsigned DEFAULT NULL,
     `updated` double DEFAULT NULL,
     `parent_conversation` tinyint(1) unsigned DEFAULT NULL,
     `creator` varchar(191) DEFAULT NULL,
     `is_ext_shared` tinyint(1) unsigned DEFAULT NULL,
     `is_member` tinyint(1) unsigned DEFAULT NULL,
     `topic_value` varchar(191) DEFAULT NULL,
     `topic_creator` varchar(191) DEFAULT NULL,
     `topic_last_set` int(11) unsigned DEFAULT NULL,
     `purpose_value` varchar(191) DEFAULT NULL,
     `purpose_creator` varchar(191) DEFAULT NULL,
     `purpose_last_set` int(11) unsigned DEFAULT NULL,
     `num_members` int(11) unsigned DEFAULT NULL,
     PRIMARY KEY (`id`),
     UNIQUE KEY `identifier` (`identifier`),
     KEY `name` (`name`),
     KEY `is_channel` (`is_channel`),
     KEY `is_group` (`is_group`),
     KEY `is_im` (`is_im`),
     KEY `is_mpim` (`is_mpim`),
     KEY `is_private` (`is_private`),
     KEY `is_member` (`is_member`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Set some API parameters
$types = 'public_channel,private_channel,mpim,im'; // mpim: multi-party instant message (aka group chats). im: instant message (aka direct chat)
$limit = 100;
$cursor = '';
$params = [
    'cursor' => $cursor,
    'limit' => $limit,
    'types' => $types,
];

// Set some progress tracking variables
$loop = 0;
$numProcessedConversations = 0;

// Begin pulling conversations
do {
    $loop++;

    // Send request to Slack API and get back data
    $response = sendPostRequestToSlackApi('conversations.list', $params);
    $data = json_decode($response, true);

    // Process fetched channels
    foreach ($data['channels'] as $channel) {
        echo 'Processing conversation `' . ($channel['name'] ?? '') . '` (' . $channel['id'] . ")\n";

        // Check for existence
        $conversationsModel = R::findOneForUpdate('conversations', '`identifier` = :identifier', [':identifier' => $channel['id']]);
        if (empty($conversationsModel)) {
            $conversationsModel = R::dispense('conversations');
            echo "Conversation does not exist on DB. Inserting a new one...";
        }
        else {
            echo "Conversation exists on DB. Updating existing record...";
        }
        echo "\n";

        // Store conversation properties
        $conversationsModel->identifier = $channel['id'] ?? null;
        $conversationsModel->name = $channel['name'] ?? null;
        $conversationsModel->is_channel = $channel['is_channel'] ?? null;
        $conversationsModel->is_group = $channel['is_group'] ?? null;
        $conversationsModel->is_im = $channel['is_im'] ?? null;
        $conversationsModel->is_mpim = $channel['is_mpim'] ?? null;
        $conversationsModel->is_private = $channel['is_private'] ?? null;
        $conversationsModel->created = $channel['created'] ?? null;
        $conversationsModel->is_archived = $channel['is_archived'] ?? null;
        $conversationsModel->is_general = $channel['is_general'] ?? null;
        $conversationsModel->unlinked = $channel['unlinked'] ?? null;
        $conversationsModel->name_normalized = $channel['name_normalized'] ?? null;
        $conversationsModel->is_shared = $channel['is_shared'] ?? null;
        $conversationsModel->is_org_shared = $channel['is_org_shared'] ?? null;
        $conversationsModel->is_pending_ext_shared = $channel['is_pending_ext_shared'] ?? null;
        $conversationsModel->updated = $channel['updated'] ?? null;
        $conversationsModel->parent_conversation = $channel['parent_conversation'] ?? null;
        $conversationsModel->creator = $channel['creator'] ?? null;
        $conversationsModel->is_ext_shared = $channel['is_ext_shared'] ?? null;
        $conversationsModel->is_member = $channel['is_member'] ?? null;
        $conversationsModel->topic_value = $channel['topic']['value'] ?? null;
        $conversationsModel->topic_creator = $channel['topic']['creator'] ?? null;
        $conversationsModel->topic_last_set = $channel['topic']['last_set'] ?? null;
        $conversationsModel->purpose_value = $channel['purpose']['value'] ?? null;
        $conversationsModel->purpose_creator = $channel['purpose']['creator'] ?? null;
        $conversationsModel->purpose_last_set = $channel['purpose']['last_set'] ?? null;
        $conversationsModel->num_members = $channel['num_members'] ?? null;

        R::store($conversationsModel);

        $numProcessedConversations++;

        echo "Processed $numProcessedConversations conversation(s)\n\n";
    }

    // Next page?
    $nextCursor = $data['response_metadata']['next_cursor'];
    $params['cursor'] = $nextCursor;

    echo "\n";

    echo "Sleeping for $sleepTime second(s)...\n";
    sleep($sleepTime);
}
while( strlen($nextCursor) > 0 /*&& $loop < 3*/ );

echo "\n";
