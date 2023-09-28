<?php

namespace vaersaagod\assetmate\services;

use craft\base\Component;
use craft\elements\Asset;
use craft\helpers\Image;
use vaersaagod\assetmate\AssetMate;
use vaersaagod\assetmate\models\Settings;
use vaersaagod\assetmate\models\VolumeSettings;
use yii\base\InvalidConfigException;

/**
 * Convert Service
 *
 * @author    Værsågod
 * @package   AssetMate
 * @since     2.3.0
 */
class Convert extends Component
{
    public function maybeConvert(Asset $asset): void
    {
        $path = $asset->tempFilePath;

        if (!$path) {
            return;
        }

        try {
            $volume = $asset?->getVolume()->handle;
        } catch (InvalidConfigException) {
            return;
        }
        
        if ($volume === null) {
            return;
        }

        $shouldConvert = $this->getShouldConvert($volume);

        if (!$shouldConvert) {
            return;
        }

        if (Image::canManipulateAsImage(@pathinfo($path, PATHINFO_EXTENSION))) {
            return;
        }
        
        if (!\Craft::$app->getImages()->getIsImagick()) {
            return;
        }

        $this->convert($asset);
    }

    public function convert(Asset $asset): void
    {
        $path = $asset->tempFilePath;
        $pathInfo = pathinfo($path);
        $newPath = $pathInfo['dirname'].DIRECTORY_SEPARATOR.$pathInfo['filename'].'.jpg';
        
        try {
            $image = new \Imagick($path.'[0]');
            $image->setImageFormat('jpg');
            $image->writeImage($newPath);
        } catch(\Throwable $throwable) {
            \Craft::error('An error occured when trying to convert image format: ' . $throwable->getMessage(), __METHOD__);
            return;
        }
        
        $asset->tempFilePath = $newPath;
        $asset->newFilename = pathinfo($asset->filename, PATHINFO_FILENAME).'.jpg';
        $asset->newLocation = str_replace(pathinfo($asset->filename, PATHINFO_BASENAME), $asset->newFilename, $asset->newLocation);
        
        // remove original file
        @unlink($path);
    }

    public function getShouldConvert(string $volume): bool
    {
        /** @var Settings $config */
        $config = AssetMate::$plugin->getSettings();
        $volumes = $config->volumes;

        $defaultConfig = new VolumeSettings($volumes['*'] ?? []);
        $volumeConfig = new VolumeSettings($volumes[$volume] ?? []);

        return $volumeConfig->convertUnmanipulable ?? $defaultConfig->convertUnmanipulable ?? false;
    }

}
