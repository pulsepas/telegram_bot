<?php
/**
 * Created by PhpStorm.
 * User: snark | itfrogs.ru
 * Date: 6/2/18
 * Time: 9:05 PM
 */

use GuzzleHttp\Client;
use Telegram\Bot\HttpClients\GuzzleHttpClient;
use Telegram\Bot\TelegramClient;
use Telegram\Bot\BotsManager;

/**
 * Class telegramAssistantPluginApi
 */
class telegramAssistantPluginApi extends telegramApi
{
    /**
     * @var
     */
    private static $settings;

    /**
     * @var
     */
    private $telegram_user_model;

    /**
     * @var
     */
    private $model;

    /**
     * @var telegramAssistantPlugin $plugin
     */
    private $plugin;

    /**
     * @var waView $view
     */
    private $view;

    /**
     * @var null
     */
    private $locale_path = null;

    /**
     * telegramAssistantPluginFrontendBotController constructor.
     * @throws \Telegram\Bot\Exceptions\TelegramSDKException
     * @throws waException
     */
    public function __construct()
    {
        $this->plugin = wa('telegram')->getPlugin('assistant');
        self::$settings = $this->plugin->getSettings();

        foreach (self::$settings as $key => $s) {
            if (!is_array($s) && stripos($key, '_title')) {
                self::$settings[$key] = base64_decode($s);
            }
        }

        $this->view = waSystem::getInstance()->getView();
        $this->telegram_user_model = new telegramAssistantPluginUserModel();
        $this->model = new waModel();

        $options = array(
            'headers' => [
                'User-Agent' => 'Telegram Bot PHP SDK v' . telegramApi::VERSION . ' - (https://github.com/irazasyed/telegram-bot-sdk)',
            ],
        );

        if (self::$settings['use_socks5']) {
            $proxy = 'socks5://';
            if (!empty(self::$settings['socks5_user']) && !empty(self::$settings['socks5_password'])) {
                $proxy .= self::$settings['socks5_user'] . ':' . self::$settings['socks5_password'] . '@';
            } elseif (!empty(self::$settings['socks5_user']) && empty(self::$settings['socks5_password'])) {
                $proxy .= self::$settings['socks5_user'] . '@';
            }

            if (!empty(self::$settings['socks5_address']) && !empty(self::$settings['socks5_port'])) {
                $proxy .= self::$settings['socks5_address'] . ':' . self::$settings['socks5_port'];
            } else {
                unset($proxy);
            }

            if (isset($proxy)) {
                $options['curl'] = array(
                    CURLOPT_PROXY => $proxy,
                    CURLOPT_HTTPPROXYTUNNEL => 1,
                    CURLOPT_HEADER => false,
                    CURLOPT_RETURNTRANSFER => 1,
                    CURLOPT_POST => 1,
                    CURLOPT_SSL_VERIFYPEER => false,

                );
            }
        }

        try {
            $httpClientHandler = $this->getGuzzleClientHandler($options);
        }
        catch (Exception $exception) {
            if (waSystemConfig::isDebug()) {
                waLog::dump($exception->getMessage(), 'telegram/assiatant-get-http-client.log');
            }
        }

        $this->setClient(new TelegramClient($httpClientHandler));

        parent::__construct(self::$settings['key'], false, $httpClientHandler);
    }

    public static function getActionsArray() {
        $rows = 5;
        $columns = 3;

        $actions = array();

        for ($row = 1; $row <= $rows; $row++) {
            for ($column = 1; $column <= $columns; $column++) {
                $button_type = self::$settings['button_' . $row . '_' . $column . '_type'];
                if ($button_type != 'off') {
                    $actions[self::$settings['button_' . $row . '_' . $column . '_action']] = array(
                        'type'      => $button_type,
                        'title'     => self::$settings['button_' . $row . '_' . $column . '_title'],
                        'content'   => self::$settings['button_' . $row . '_' . $column . '_content'],
                        'path'      => self::$settings['button_' . $row . '_' . $column . '_path'],
                    );
                }
            }
        }

        return $actions;
    }

    public function getActions() {
        return telegramAssistantPluginApi::getActionsArray();
    }

    public static function getButtonsArray() {
        $rows = 5;
        $columns = 3;

        $actions = self::getActionsArray();
        $buttons = array();

        for ($row = 1; $row <= $rows; $row++) {
            $buttons[$row - 1] = array();
            for ($column = 1; $column <= $columns; $column++) {
                $button_type = self::$settings['button_' . $row . '_' . $column . '_type'];
                $button_action = self::$settings['button_' . $row . '_' . $column . '_action'];
                if ($button_type != 'off' && isset($actions[$button_action])) {
                    $button = array();
                    if ($button_type == 'link') {
                        $button = array(
                            [
                                'text' => self::$settings['button_' . $row . '_' . $column . '_title'],
                                'url' => self::$settings['button_' . $row . '_' . $column . '_content'],
                            ]
                        );
                    }
                    else {
                        $button = array(
                            [
                                'text' => self::$settings['button_' . $row . '_' . $column . '_title'],
                                'callback_data' => $button_action,
                            ]
                        );
                    }

                    if (!empty($button)) {
                        $buttons[$row - 1] = array_merge($buttons[$row - 1], $button);
                    }
                }
            }
            if (empty($buttons[$row - 1])) unset($buttons[$row - 1]);
        }

        return $buttons;
    }

    /**
     * @param $params
     * @throws waException
     */
    public function botSendMessage($params)
    {
        if ($params['chat_id'] < 0 || $params['is_group']) {
            return;
        }

        $user = $this->telegram_user_model->getById($params['chat_id']);

        if (!$user['blocked']) {
            try {
                $this->sendMessage($params);
            } catch (Exception $e) {
                if ($e->getMessage() && $e->getMessage() == 'Forbidden: bot was blocked by the user') {
                    //$this->botStop($params);
                    $user['blocked'] = 1;
                    $user = $this->telegram_user_model->updateById($user['chat_id'], $user);
                    if (waSystemConfig::isDebug()) {
                        waLog::log('Пользователь ' . !empty($user['name']) ? $user['name'] : $user['chat_id'] . ' забанил бота и был отписан.',
                            'telegram/assistant-ban.log');
                    }
                } else {
                    if (waSystemConfig::isDebug()) {
                        waLog::dump($params, 'telegram/assistant-exception.log');
                        waLog::dump($e->getMessage(), 'telegram/assistant-exception.log');
                    }
                }
            }
        }
    }

    /**
     * @throws waException
     */
    public function botSendPhoto($params) {
        $user = $this->telegram_user_model->getById($params['chat_id']);
        $path = $params['action']['path'];
        $this->sendPhoto([ 'chat_id' => $params['chat_id'], 'photo'=> $path, 'caption' => $params['action']['content'] ]);
    }

    /**
     * @throws waException
     */
    public function botSendDocument($params) {
        $user = $this->telegram_user_model->getById($params['chat_id']);
        $path = $params['action']['path'];

        $this->sendDocument([ 'chat_id' => $params['chat_id'], 'document'=> $path, 'reply_markup' => $this->getReplyMarkup($params) ]);
    }

    /**
     * @throws waException
     */
    public function botSendAudio($params) {
        $user = $this->telegram_user_model->getById($params['chat_id']);
        $path = $params['action']['path'];
        $this->sendAudio([ 'chat_id' => $params['chat_id'], 'audio'=> $path, 'title' => $params['action']['content'], 'reply_markup' => $this->getReplyMarkup($params) ]);
    }

    /**
     * @throws waException
     */
    public function botSendVideo($params) {
        $user = $this->telegram_user_model->getById($params['chat_id']);
        $path = $params['action']['path'];
        $this->sendVideo([ 'chat_id' => $params['chat_id'], 'video'=> $path, 'caption' => $params['action']['content'], 'reply_markup' => $this->getReplyMarkup($params) ]);
    }

    /**
     * @throws waException
     */
    public function botSendVoice($params) {
        $user = $this->telegram_user_model->getById($params['chat_id']);
        $path = $params['action']['path'];
        $this->sendVoice([ 'chat_id' => $params['chat_id'], 'voice'=> $path, 'reply_markup' => $this->getReplyMarkup($params)  ]);
    }

    /**
     * @param $params
     * @throws waException
     */
    public function botStart($params)
    {
        $user = $this->checkUser($params);
        if ($user['blocked'] == 1) {
            $user['blocked'] = 0;
            $user = $this->telegram_user_model->updateById($user['chat_id'], $user);
        }

        $this->view->assign('user', $user);
        $text = self::$settings['welcome'];
        $reply_markup = $this->getReplyMarkup($params);
        $send_params = [
            'chat_id' => $params['chat_id'],
            'text' => $text,
            'parse_mode' => 'HTML',
            'reply_markup' => $reply_markup,
            'is_group' => $params['is_group'],
        ];

        $this->botSendMessage($send_params);
    }


    /**
     * @param $params
     * @return array|null
     * @throws waException
     */
    public function checkUser($params)
    {
        $user = $this->telegram_user_model->getById($params['chat_id']);

        if (empty($user) && $params['chat_id'] > 0) {
            $user = array(
                'chat_id'       => $params['chat_id'],
                'name'          => $params['name'],
                'in_group'      => false,
                'blocked'       => 0,
            );

            if (isset($params['is_bot'])) {
                $user['is_bot'] = $params['is_bot'];
            }

            try {
                $this->telegram_user_model->insert($user);
            } catch (Exception $e) {
                if (waSystemConfig::isDebug()) {
                    waLog::log("PARAMS:", 'telegram/assistant-insert-errors.log');
                    waLog::log(print_r($params,true), 'telegram/assiatant-insert-errors.log');
                    if (isset($link)) {
                        waLog::log("LINK:", 'telegram/assiatant-insert-errors.log');
                        waLog::log(print_r($link,true), 'telegram/assiatant-insert-errors.log');
                    }
                    waLog::log("USER:", 'telegram/assiatant-insert-errors.log');
                    waLog::log(print_r($user,true), 'telegram/assiatant-insert-errors.log');
                    waLog::log("================", 'telegram/assiatant-insert-errors.log');

                }
            }
        }
        return $user;
    }


    /**
     * @param $params
     * @return string
     * @throws waException
     */
    public function getReplyMarkup($params)
    {
        $user = $this->checkUser($params);

        $keyboard = self::getButtonsArray();

        $reply_markup = $this->replyKeyboardMarkup(
            [
                'inline_keyboard' => $keyboard,
                'resize_keyboard' => true,
                'one_time_keyboard' => false,
            ]
        );

        return $reply_markup;
    }

    /**
     * @param $params
     * @throws waException
     */
    public function botAction($params)
    {
        $reply_markup = $this->getReplyMarkup($params);

        $send_params = [
            'chat_id' => $params['chat_id'],
            'text' => $this->view->fetch('string:' . $params['action']['content']),
            'parse_mode' => 'HTML',
            'reply_markup' => $reply_markup,
            'is_group' => $params['is_group'],
        ];

        $this->botSendMessage($send_params);
    }

}