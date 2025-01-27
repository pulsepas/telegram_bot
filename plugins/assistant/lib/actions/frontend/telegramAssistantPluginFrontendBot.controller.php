<?php

/**
 * Class telegramAssistantPluginFrontendBotController
 */

class telegramAssistantPluginFrontendBotController extends waController
{
    /**
     * @var
     */
    private $settings;

    /**
     * @var
     */
    private $params = array(
        'is_group'  => false,
        'locale'    => null,
    );

    /**
     * @var telegramApi
     */
    private $telegram;

    /**
     * @var telegramAssistantPlugin $plugin
     */
    private static $plugin;

    /**
     * @return telegramAssistantPlugin|waPlugin
     * @throws waException
     */
    private static function getPlugin()
    {
        if (!isset(self::$plugin)) {
            self::$plugin = wa('telegram')->getPlugin('assistant');
        }
        return self::$plugin;
    }

    /**
     * @var waView $view
     */
    private static $view;

    /**
     * @return waSmarty3View|waView
     * @throws waException
     */
    private static function getView()
    {
        if (!isset(self::$view)) {
            self::$view = waSystem::getInstance()->getView();
        }
        return self::$view;
    }


    /**
     * telegramAssistantPluginFrontendBotController constructor.
     * @throws \Telegram\Bot\Exceptions\TelegramSDKException
     * @throws waException
     */
    public function __construct()
    {
        $plugin = self::getPlugin();
        $this->settings = $plugin->getSettings();
        $this->telegram = new telegramAssistantPluginApi();
    }

    /**
     * @throws waException
     */
    public function execute()
    {

        //Передаем в переменную $result полную информацию о сообщении пользователя
        $result = $this->telegram->getWebhookUpdate();

        $result_array = $result->toArray();

        if (waSystemConfig::isDebug()) {
            waLog::dump($result_array, 'telegram/assistant-result-array.log');
        }

        $message = $result->getMessage();

        $user_model = new telegramAssistantPluginUserModel();
        $model = new waModel();

        $sended = false;
        $text = "Отправьте текстовое сообщение.";

        //Проверяем точно ли к нам стучится телеграм. Если в запросе есть необходимые данные, то выполняем действия.
        if(!empty($result)){
            $message_text = null;
            $this->params['is_group'] = false;
            $this->params['locale'] = wa()->getLocale();

            if (!empty($message)) {
                $message_array = $message->toArray();
                if (waSystemConfig::isDebug()) {
                    waLog::dump($message_array, 'telegram/assistant-message-array.log');
                }

                //$message_text = isset($message_array['text']) ? $message_array['text'] : '';
                $message_text = isset($result_array['callback_query']['data']) ? $result_array['callback_query']['data'] : '';
                $chat_id = $message_array['chat']['id'];

                $is_bot = $message_array['from']['is_bot'];

                if (isset($message_array['from']['username'])) {
                    $name = $message_array['from']['username'];
                }
                else {
                    $name = '';
                }

                if (isset($message_array['from']['first_name'])) {
                    $first_name = $message_array['from']['first_name'];
                }
                else {
                    $first_name = '';
                }

                if (isset($message_array['from']['last_name'])) {
                    $last_name = $message_array['from']['last_name'];
                }
                else {
                    $last_name = '';
                }
            }
            //В случае если мы получили данные из канала
            elseif (isset($result_array['channel_post']) && !empty($result_array['channel_post'])) {
                $channel_post = $result_array['channel_post'];
                if (isset($channel_post['chat']) && isset($channel_post['chat']['id']) && isset($channel_post['chat']['username']) && $channel_post['chat']['id'] < 0) {

                }
            }
            else {
                $message_text = $result_array['callback_query']['data'];
                $chat_id = $result_array['callback_query']['from']['id'];
                $is_bot = $result_array['callback_query']['from']['is_bot'];
                if (isset($result_array['callback_query']['from']['username'])) {
                    $name = $result_array['callback_query']['from']['username'];
                }
                else {
                    $name = '';
                }

                if (isset($result_array['callback_query']['from']['first_name'])) {
                    $first_name = $result_array['callback_query']['from']['first_name'];
                }
                else {
                    $first_name = '';
                }

                if (isset($result_array['callback_query']['from']['last_name'])) {
                    $last_name = $result_array['callback_query']['from']['last_name'];
                }
                else {
                    $last_name = '';
                }

                if (isset($result_array['callback_query'])
                    && isset($result_array['callback_query']['from'])
                    && isset($result_array['callback_query']['from']['language_code'])
                    && !empty($result_array['callback_query']['from']['language_code'])
                ) {
                    $this->params['locale'] = $result_array['callback_query']['from']['language_code'];
                }

                if (!$this->params['locale']) $this->params['locale'] = 'en';
            }

            $this->params['is_bot']     = $is_bot;
            $this->params['text']       = $message_text;
            $this->params['chat_id']    = $chat_id;
            $this->params['name']       = $name;
            $this->params['first_name'] = $first_name;
            $this->params['last_name']  = $last_name;

            $args = explode(' ', $this->params['text']);

            $actions = $this->telegram->getActions();

            if (isset($args[0]) && in_array($args[0], array('/start', 'start'))) {
                if (isset($args[1])) {
                    $this->params['start_arg'] = $args[1];
                }
                else {
                    $this->params['start_arg'] = 0;
                }

                $this->telegram->botStart($this->params);
            }
            elseif (isset($actions[$this->params['text']])) {
                $action = $actions[$this->params['text']];
                $this->params['action'] = $actions[$this->params['text']];
                if ($action['type'] == 'action') {
                    $this->telegram->botAction($this->params);
                }
                elseif ($action['type'] == 'doc') {
                    $this->telegram->botSendDocument($this->params);
                }
                elseif ($action['type'] == 'photo') {
                    $this->telegram->botSendPhoto($this->params);
                }
                elseif ($action['type'] == 'autio') {
                    $this->telegram->botSendAudio($this->params);
                }
                elseif ($action['type'] == 'video') {
                    $this->telegram->botSendVideo($this->params);
                }
                elseif ($action['type'] == 'voice') {
                    $this->telegram->botSendVoice($this->params);
                }
            }
            else {
                $user = $this->telegram->checkUser($this->params);
                $reply_markup = $this->telegram->getReplyMarkup($this->params);

                if (!$sended) {
                    $send_params = [
                        'chat_id' => $this->params['chat_id'],
                        'text' => $text,
                        'parse_mode'=> 'HTML',
                        'reply_markup' => $reply_markup,
                        'is_group' => isset($this->params['is_group']) ? $this->params['is_group'] : false,
                    ];

                    $this->telegram->botSendMessage($send_params);
                }

            }

        }
        else{
            $text = "Отправьте текстовое сообщение.";
            $reply_markup = $this->telegram->getReplyMarkup($this->params);
            $send_params = [
                'chat_id' => $this->params['chat_id'],
                'text' => $text,
                'parse_mode'=> 'HTML',
                'reply_markup' => $reply_markup,
                'is_group' => $this->params['is_group'],
            ];

            $this->telegram->botSendMessage($send_params);
        }

        return false;
    }
}