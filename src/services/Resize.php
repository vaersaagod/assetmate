<?php

namespace vaersaagod\assetmate\services;

use craft\base\Component;
use craft\elements\Asset;
use craft\errors\ImageException;
use craft\helpers\Image;
use vaersaagod\assetmate\AssetMate;
use vaersaagod\assetmate\models\ResizeSettings;
use vaersaagod\assetmate\models\Settings;
use vaersaagod\assetmate\models\VolumeSettings;
use yii\base\InvalidConfigException;

/**
 * Resize Service
 *
 * @author    Værsågod
 * @package   AssetMate
 * @since     2.1.0
 */
class Resize extends Component
{
    public function maybeResize(Asset $asset): void
    {
        $path = $asset->tempFilePath;

        if (!$path) {
            return;
        }

        try {
            $volume = $asset->getVolume()->handle;
        } catch (InvalidConfigException) {
            return;
        }

        $config = $this->getResizeConfig($volume);

        if (!isset($config->maxWidth, $config->maxWidth)) {
            return;
        }

        $this->resize($asset, $config);
    }

    public function resize(Asset $asset, ResizeSettings $config): void
    {
        $filename = $asset->filename;
        $path = $asset->tempFilePath;

        // Is this a manipulatable image?
        if (!Image::canManipulateAsImage(@pathinfo($path, PATHINFO_EXTENSION))) {
            return;
        }

        try {
            $image = \Craft::$app->images->loadImage($path);

            $original = [
                'width' => $image->getWidth(),
                'height' => $image->getHeight(),
                'size' => filesize($path),
            ];

            if (
                (isset($config->maxWidth) && !isset($config->maxHeight) && $image->getWidth() < $config->maxWidth) ||
                (!isset($config->maxWidth) && isset($config->maxHeight) && $image->getHeight() < $config->maxHeight) ||
                (isset($config->maxWidth, $config->maxHeight) && $image->getWidth() < $config->maxWidth && $image->getHeight() < $config->maxHeight)
            ) {
                return;
            }
            
            $dimensions = [];
            
            if (isset($config->maxWidth, $config->maxHeight)) {
                if ($image->getWidth() > $image->getHeight()) {
                    $dimensions = Image::calculateMissingDimension($config->maxWidth, null, $image->getWidth(), $image->getHeight());
                } else {
                    $dimensions = Image::calculateMissingDimension(null, $config->maxHeight, $image->getWidth(), $image->getHeight());
                }
            } elseif (isset($config->maxWidth)) {
                $dimensions = Image::calculateMissingDimension($config->maxWidth, null, $image->getWidth(), $image->getHeight());
            } elseif (isset($config->maxHeight)) {
                $dimensions = Image::calculateMissingDimension(null, $config->maxHeight, $image->getWidth(), $image->getHeight());
            }
            
            if (empty($dimensions)) {
                return;
            }
            
            $image->resize($dimensions[0], $dimensions[1]);
            
            if (method_exists($image, 'setQuality')) {
                $image->setQuality($this->getImageQuality($path, $config));
            }
            
            $this->save($image, $path);
            
        } catch (\Throwable $throwable) {
            \Craft::error('An error occured when trying to resize Asset: '.$throwable->getMessage(), __METHOD__);
        }
    }

    /**
     * @param \craft\base\Image $image
     * @param string            $path
     *
     * @return void
     * @throws ImageException
     */
    public function save(\craft\base\Image &$image, string $path): void
    {
        // Get the current orientation from Exif - we might need this later to rotate
        $orientation = $image->getImagineImage()?->metadata()->get('ifd0.Orientation');

        $degrees = false;

        switch ($orientation) {
            case Image::EXIF_IFD0_ROTATE_180:
                $degrees = 180;
                break;
            case Image::EXIF_IFD0_ROTATE_90:
                $degrees = 90;
                break;
            case Image::EXIF_IFD0_ROTATE_270:
                $degrees = 270;
                break;
        }

        // Save the resized image. Note that this can potentially strip all Exif metadata (with `preserveExifData = false`).
        // We need to do this ASAP, because this is the in-memory, resized image. All other operations such as stripping
        // Exif data or rotating images need an on-file, saved image to mess around with.
        $image->saveAs($path);

        // If we want to `rotateImagesOnUploadByExifData` we will need to do this manually, rather than rely on
        // `rotateImageByExifData()` because that will try and load the image again, but because it's aready saved
        // above, there's no Exif orientation data to look at. Fortunately, we've captured that already before the save.
        if ($degrees && \Craft::$app->getConfig()->getGeneral()->rotateImagesOnUploadByExifData) {
            // Load in the image again, fresh (it's been resized after all)
            $image = \Craft::$app->getImages()->loadImage($path);

            // Perform the rotate and save again
            $image->rotate($degrees);
            $image->saveAs($path);
        }
    }

    public function getResizeConfig(string $volume): ResizeSettings
    {
        /** @var Settings $config */
        $config = AssetMate::$plugin->getSettings();
        $volumes = $config->volumes;

        $defaultConfig = new VolumeSettings($volumes['*'] ?? []);
        $volumeConfig = new VolumeSettings($volumes[$volume] ?? []);

        $r = array_merge($defaultConfig->resize, $volumeConfig->resize);

        return new ResizeSettings($r);
    }

    public function getImageQuality($path, ResizeSettings $config): int
    {
        // tbd : maybe improve config (per format) at some point
        
        if (@pathinfo($path, PATHINFO_EXTENSION) === 'png') {
            return 2;
        }
        
        return $config->quality;
    }
}
