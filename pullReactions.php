<?php

require_once 'init.php';

echo <<<PULLING_REACTIONS
================================================
                PULLING REACTIONS
================================================

PULLING_REACTIONS;

$sleepTime = 1;

// Create table
R::exec("
CREATE TABLE IF NOT EXISTS `reactions` (
     `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
     `message_ts` varchar(191) DEFAULT NULL,
     `user_identifier` varchar(191) DEFAULT NULL,
     `emoji_name` varchar(191) DEFAULT NULL,
     PRIMARY KEY (`id`),
     UNIQUE KEY `message_ts` (`message_ts`,`user_identifier`,`emoji_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Get messages with reactions
$messagesWithReactions = R::getAll('SELECT `channel_identifier`, `ts` FROM `messages` WHERE `no_reactions` = 0');
$messagesWithReactionCount = count($messagesWithReactions);
$numProcessedMessages = 0;

foreach ($messagesWithReactions as $message) {
    $channelIdentifier = $message['channel_identifier'];
    $ts = $message['ts'];

    echo "Pulling reactions for message $message[ts]\n";

    // Set some API parameters
    $params = [
        'channel' => $channelIdentifier,
        'ts' => $ts,
        'limit' => 1000,
    ];

    // Send request to Slack API and get back data
    $response = sendPostRequestToSlackApi('conversations.replies', $params);
    $data = json_decode($response, true);

    if (isset($data['messages'][0]['reactions']) && count($data['messages'][0]['reactions']) > 0) {
        // Delete existing reactions
        R::exec('DELETE FROM `reactions` WHERE `message_ts` = :message_ts', [':message_ts' => $ts]);

        // Store reactions into DB
        $reactionCount = 0;
        foreach ($data['messages'][0]['reactions'] as $reaction) {
            foreach ($reaction['users'] as $userIdentifier) {
                $reactionsModel = R::dispense('reactions');
                $reactionsModel->message_ts = $ts;
                $reactionsModel->user_identifier = $userIdentifier;
                $reactionsModel->emoji_name = $reaction['name'] ?? null;

                R::store($reactionsModel);
                $reactionCount++;
            }
        }

        echo "Stored $reactionCount reaction(s)\n";
    }

    $numProcessedMessages++;
    echo "Processed $numProcessedMessages of $messagesWithReactionCount messages\n";

    echo "\n";

    echo "Sleeping for $sleepTime second(s)...\n";
    sleep($sleepTime);
}
