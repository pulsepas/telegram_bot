<?php
return array(
    'telegram_assistant_user' => array(
        'chat_id' => array('int', 11, 'null' => 0),
        'name' => array('varchar', 50, 'null' => 0),
        'place' => array('enum', "'main','wallet','withdraw','paysystems'", 'default' => 'main'),
        'blocked' => array('tinyint', 1, 'default' => '0'),
        'is_bot' => array('tinyint', 1, 'default' => '0'),
        ':keys' => array(
            'PRIMARY' => 'chat_id',
        ),
    ),
);
