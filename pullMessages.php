<?php

require_once 'init.php';

echo <<<PULLING_MESSAGES
================================================
                PULLING MESSAGES
================================================

PULLING_MESSAGES;

$sleepTime = 3;

// Create tables
R::exec("
CREATE TABLE IF NOT EXISTS `messages` (
     `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
     `channel_identifier` varchar(191) DEFAULT NULL,
     `type` varchar(191) DEFAULT NULL,
     `user_identifier` varchar(191) DEFAULT NULL,
     `ts` varchar(191) DEFAULT NULL,
     `text` text DEFAULT NULL,
     `blocks` text DEFAULT NULL,
     `permalink` varchar(191) DEFAULT NULL,
     `parent_thread_ts` varchar(191) DEFAULT NULL,
     `no_reactions` tinyint(1) unsigned DEFAULT NULL,
     PRIMARY KEY (`id`),
     UNIQUE KEY `ts` (`ts`),
     KEY `channel_identifier` (`channel_identifier`),
     KEY `user_identifier` (`user_identifier`),
     KEY `parent_thread_ts` (`parent_thread_ts`),
     KEY `no_reactions` (`no_reactions`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

R::exec("
CREATE TABLE IF NOT EXISTS `files` (
     `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
     `identifier` varchar(191) DEFAULT NULL,
     `message_ts` varchar(191) DEFAULT NULL,
     `created` int(11) unsigned DEFAULT NULL,
     `timestamp` int(11) unsigned DEFAULT NULL,
     `name` varchar(191) DEFAULT NULL,
     `title` varchar(191) DEFAULT NULL,
     `mimetype` varchar(191) DEFAULT NULL,
     `filetype` varchar(191) DEFAULT NULL,
     `pretty_type` varchar(191) DEFAULT NULL,
     `size` int(11) unsigned DEFAULT NULL,
     `mode` varchar(191) DEFAULT NULL,
     `url_private` varchar(191) DEFAULT NULL,
     `url_private_download` varchar(191) DEFAULT NULL,
     `permalink` varchar(191) DEFAULT NULL,
     `file_access` varchar(191) DEFAULT NULL,
     `file_local_path` varchar(191) DEFAULT NULL,
     PRIMARY KEY (`id`),
     UNIQUE KEY `identifier` (`identifier`),
     KEY `message_ts` (`message_ts`),
     KEY `name` (`name`),
     KEY `title` (`title`),
     KEY `filetype` (`filetype`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Find existing conversations
$conversations = R::findAll('conversations');

foreach ($conversations as $conversation) {
    echo "Processing conversation $conversation[name]\n";

    // Set some API parameters
    $count = 100;
    $cursor = '*'; // per the Slack API doc, should send * on the first loop

    $params = [
        'query' => "in:#$conversation[name]", // Ex: "in:#general"
        'cursor' => $cursor,
        'count' => $count,
        'sort' => 'timestamp',
    ];

    $loop = 0;

    do {
        $loop++;

        echo "Loop $loop:\n";

        $startTime = time();

        $response = sendPostRequestToSlackApi('search.messages', $params);
        $data = json_decode($response, true);

        foreach ($data['messages']['matches'] as $message) {
            echo 'Processing message ' . basename($message['permalink']) . "\n";

            $messagesModel = R::findOneForUpdate('messages', '`ts` = :ts', [':ts' => $message['ts']]);

            if (empty($messagesModel)) {
                $messagesModel = R::dispense('messages');
                echo "Message does not exist on DB. Inserting a new one...\n";
            }
            else {
                echo "Message exists on DB. Updating existing record...\n";
            }

            $messagesModel->channel_identifier = $message['channel']['id'] ?? null;
            $messagesModel->type = $message['type'] ?? null;
            $messagesModel->user_identifier = $message['user'] ?? null;
            $messagesModel->ts = $message['ts'] ?? null; // This is the unique identifier!
            $messagesModel->text = $message['text'] ?? null;
            $messagesModel->blocks = isset($message['blocks']) ? json_encode($message['blocks']) : null;
            $messagesModel->permalink = $message['permalink'] ?? null;

            // Check if the message is the main thread or a reply
            $messagesModel->parent_thread_ts = 0; // set to 0 by default
            $messagePermalink = $message['permalink'];
            if (strpos($messagePermalink, '?thread_ts') !== false) {
                $parentThreadTS = extractThreadTsFromSlackPermalink($messagePermalink);
                if ($parentThreadTS != $message['ts']) { // this is a reply
                    $messagesModel->parent_thread_ts = $parentThreadTS;
                }
            }

            // Check if having reactions
            if (isset($message['no_reactions'])) {
                $messagesModel->no_reactions = $message['no_reactions'];
            }
            else {
                $messagesModel->no_reactions = false;
            }

            R::store($messagesModel);

            // Download files attached to the message (if any)
            if (isset($message['files'])) {
                echo 'Downloading ' . count($message['files']) . " files\n";

                // Process files
                foreach ($message['files'] as $file) {
                    $filesModel = R::findOneForUpdate('files', '`identifier` = :identifier', [':identifier' => $file['id']]);
                    if (empty($filesModel)) {
                        $filesModel = R::dispense('files');
                    }

                    $filesModel->identifier = $file['id'] ?? null;
                    $filesModel->message_ts = $message['ts'] ?? null;
                    $filesModel->created = $file['created'] ?? null;
                    $filesModel->timestamp = $file['timestamp'] ?? null;
                    $filesModel->name = $file['name'] ?? null;
                    $filesModel->title = $file['title'] ?? null;
                    $filesModel->mimetype = $file['mimetype'] ?? null;
                    $filesModel->filetype = $file['filetype'] ?? null;
                    $filesModel->pretty_type = $file['pretty_type'] ?? null;
                    $filesModel->size = $file['size'] ?? null;
                    $filesModel->mode = $file['mode'] ?? null;
                    $filesModel->url_private = $file['url_private'] ?? null;
                    $filesModel->url_private_download = $file['url_private_download'] ?? null;
                    $filesModel->permalink = $file['permalink'] ?? null;
                    $filesModel->file_access = $file['file_access'] ?? null;

                    // Download file
                    $fileUrl = $file['url_private_download'];
                    $targetDir = 'files' . DIRECTORY_SEPARATOR . $conversation['name'];
                    if (!is_dir($targetDir)) {
                        mkdir($targetDir, 0777, true);
                    }

                    if (validUrl($fileUrl)) {
                        $targetPath = $targetDir . DIRECTORY_SEPARATOR . $file['id'] . '-' . $file['name'];
                        if (!file_exists($targetPath)) {
                            if (downloadSlackFile($fileUrl, $targetPath) && file_exists($targetPath)) {
                                $filesModel->file_local_path = $targetPath;
                            }
                        }
                        else {
                            $filesModel->file_local_path = $targetPath;
                        }
                    }

                    R::store($filesModel);
                }
            }

            echo "\n";
        }

        echo 'Processed ' . ($loop * $count) . ' out of ' . $data['messages']['total'] . " messages\n";

        // Next page?
        $nextCursor = $data['messages']['pagination']['next_cursor'];
        $params['cursor'] = $nextCursor;

        echo "\n";

        $passedSeconds = time() - $startTime;
        echo "$passedSeconds second(s) has passed. ";

        if ($passedSeconds >= $sleepTime) {
            echo "No need to sleep.\n";
        }
        else {
            $timeNeedToSleep = $sleepTime - $passedSeconds;
            echo "Sleeping for $timeNeedToSleep second(s)...\n";
            sleep($timeNeedToSleep);
        }

        echo "\n";
    }
    while( strlen($nextCursor) > 0 /*&& $loop < 3*/ );
}

echo "\n";

