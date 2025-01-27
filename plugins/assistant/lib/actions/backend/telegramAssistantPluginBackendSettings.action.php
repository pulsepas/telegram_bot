<?php
/**
 * Created by PhpStorm.
 * User: snark | itfrogs.ru
 * Date: 3/6/16
 * Time: 12:19 PM
 */

class telegramAssistantPluginBackendSettingsAction extends waViewAction
{
    /**
     * @var telegramAssistantPlugin $plugin
     */
    private static $plugin;

    /**
     * @throws waException
     */
    public function execute()
    {
        $control_params = array(
            'id'                  => waRequest::get('id'),
            'namespace'           => 'telegram_assistant',
            'title_wrapper'       => '%s',
            'description_wrapper' => '<br><span class="hint">%s</span>',
            'control_wrapper'     => '<div class="name">%s</div><div class="value">%s %s</div>'
        );

        $settings = self::$plugin->getSettings();
        $this->view->assign('settings', $settings);
        $this->view->assign('plugin_id', 'assistant');
        $this->view->assign('tabs', telegramAssistantPlugin::getTabs());
        $this->view->assign('plugin_settings_controls', $this->getPluginSettingsControls($control_params));

        $this->setTemplate(telegramAssistantPlugin::getPluginPath() . '/templates/actions/backend/settings/Settings.html');
    }

    /**
     * telegramAssistantPluginSettingsAction constructor.
     * @param null $params
     * @throws waException
     */
    public function __construct($params = NULL)
    {
        $plugin = wa('telegram')->getPlugin('assistant');
        self::$plugin = $plugin;
        parent::__construct($params);
    }

    /**
     * Возвращает элементы формы для вкладки Samples Settings
     *
     * @param array $params
     * @return array
     */
    private function getPluginSettingsControls($params)
    {
        $controls = array(
            'basic'     => self::$plugin->getControls($params + array('subject' => 'basic_settings')),
            'interface'  => self::$plugin->getControls($params + array('subject' => 'interface_settings')),
            'info'      => self::$plugin->getControls($params + array('subject' => 'info_settings')),
        );
        return $controls;
    }
}