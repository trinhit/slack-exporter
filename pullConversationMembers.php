<?php

require_once 'init.php';

echo <<<PULLING_CONVERSATION_MEMBERS
================================================
         PULLING CONVERSATION MEMBERS
================================================

PULLING_CONVERSATION_MEMBERS;

// Create table
R::exec("
CREATE TABLE IF NOT EXISTS `conversationmembers` ( # because RedBeanPHP doesn't support underscores :(
     `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
     `conversation_identifier` varchar(191) DEFAULT NULL,
     `member_identifier` varchar(191) DEFAULT NULL,
     PRIMARY KEY (`id`),
     UNIQUE KEY `conversation_identifier` (`conversation_identifier`,`member_identifier`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$numProcessedConversations = 0;

// Find existing conversations on database
$conversations = R::findAll('conversations');
$conversationCount = count($conversations);

// Find members of each conversation
foreach ($conversations as $conversation) {
    echo "Pulling members of conversation `$conversation[name]` ($conversation[identifier])...\n";

    // Set some API parameters
    $limit = 100;
    $cursor = '';
    $params = [
        'channel' => $conversation['identifier'],
        'cursor' => $cursor,
        'limit' => $limit,
    ];

    // Set some progress tracking variables
    $loop = 0;

    // Begin pulling conversation members
    do {
        $loop++;

        // Send request to Slack API and get back data
        $response = sendPostRequestToSlackApi('conversations.members', $params);
        $data = json_decode($response, true);
        $memberCount = count($data['members']);

        if ($memberCount > 0) {
            // Delete existing records
            R::exec('DELETE FROM `conversationmembers` WHERE `conversation_identifier` = :conversation_identifier', [':conversation_identifier' => $conversation['identifier']]);

            foreach ($data['members'] as $member) {
                $memberModel = R::dispense('conversationmembers');
                $memberModel->conversation_identifier = $conversation['identifier'] ?? null;
                $memberModel->member_identifier = $member ?? null;
                R::store($memberModel);
            }

            echo "Stored $memberCount member(s)\n";
        }
        else {
            echo "There is no member\n";
        }

        $nextCursor = $data['response_metadata']['next_cursor'];
        $params['cursor'] = $nextCursor;
    }
    while( strlen($nextCursor) > 0 /*|| $loop < 3*/ );

    $numProcessedConversations++;

    echo "Processed $numProcessedConversations of $conversationCount conversation(s)\n\n";
}
