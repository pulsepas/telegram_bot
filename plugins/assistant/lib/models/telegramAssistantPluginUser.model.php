<?php
/**
 * Created by PhpStorm.
 * User: snark | itfrogs.ru
 * Date: 3/14/18
 * Time: 7:26 PM
 */

class telegramAssistantPluginUserModel extends waModel
{
    /**
     * Primary key of the table
     * @var string
     */
    protected $id = 'chat_id';

    protected $table = 'telegram_assistant_user';

}