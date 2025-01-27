<?php

class telergramTellerFrontendAction extends waFrontController
{
    public function execute()
    {
       waLog::dump('hello! frontend', 'teller.log');
        $telegram = new telegramApi("6122520738:AAHozQP1iCwd6TqRQTeC8F6YEDdheVhHIrs");

 // get updates
        $updates = $telegram->getUpdates();
       waLog::dump($updates, 'teller.log');
        // iterate through each update
        foreach ($updates as $update) {
            // check if it's a message
            if ($update->getMessage()) {
                // send reply
                $telegram->sendMessage([
                    'chat_id' => $update->getMessage()->getChat()->getId(),
                    'text' => 'ok',
                ]);
	       waLog::dump('message sent!', 'teller.log');
            }
        }
    }
}