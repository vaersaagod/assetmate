<?php

namespace vaersaagod\assetmate;

use craft\base\Plugin;
use craft\elements\Asset;

use vaersaagod\assetmate\models\Settings;
use vaersaagod\assetmate\services\Validate;

use yii\base\Model;
use yii\base\ModelEvent;
use yii\base\Event;

/**
 * @author    Værsågod
 * @package   AssetMate
 * @since     1.0.0
 *
 * @property  Validate $validate
 * @property  Settings $settings
 */

class AssetMate extends Plugin
{
    // Static Properties
    // =========================================================================

    /**
     * @var AssetMate
     */
    public static AssetMate $plugin;

    // Public Properties
    // =========================================================================

    /**
     * @var string
     */
    public $schemaVersion = '1.0.0';

    // Public Methods
    // =========================================================================

    public function init(): void
    {
        parent::init();

        self::$plugin = $this;

        // Register services
        $this->setComponents([
            'validate' => Validate::class,
        ]);
        
        Event::on(
            Asset::class,
            Model::EVENT_BEFORE_VALIDATE,
            function (ModelEvent $event) {
                /** @var Asset $asset */
                $asset = $event->sender;
                $this->validate->validateAsset($asset);
            }
        );
    }

    // Protected Methods
    // =========================================================================

    /**
     * Creates and returns the model used to store the plugin’s settings.
     *
     * @return Settings
     */
    protected function createSettingsModel(): Settings
    {
        return new Settings();
    }
}
