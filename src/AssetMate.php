<?php

namespace vaersaagod\assetmate;

use craft\base\Plugin;
use craft\elements\Asset;

use craft\events\AssetEvent;
use vaersaagod\assetmate\models\Settings;
use vaersaagod\assetmate\services\Resize;
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
 * @property  Resize $resize
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

    // Public Methods
    // =========================================================================

    public function init(): void
    {
        parent::init();

        self::$plugin = $this;

        // Register services
        $this->setComponents([
            'validate' => Validate::class,
            'resize' => Resize::class,
        ]);
        
        Event::on(
            Asset::class,
            Model::EVENT_BEFORE_VALIDATE,
            function (ModelEvent $event) {
                /** @var Asset $asset */
                $asset = $event->sender;
                
                if (!$asset->propagating) {
                    $this->validate->validateAsset($asset);
                }
            }
        );
        
        Event::on(
            Asset::class, 
            Asset::EVENT_BEFORE_HANDLE_FILE, 
            function (AssetEvent $event) {
                $asset = $event->asset;
                $this->resize->maybeResize($asset);
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
