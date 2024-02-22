<?php

namespace vaersaagod\assetmate\services;

use Craft;
use craft\base\Component;
use craft\elements\Asset;
use craft\helpers\ConfigHelper;
use craft\helpers\Image;
use craft\models\Volume;

use vaersaagod\assetmate\AssetMate;
use vaersaagod\assetmate\helpers\AssetMateHelper;
use vaersaagod\assetmate\models\Settings;
use vaersaagod\assetmate\models\ValidationDimensions;
use vaersaagod\assetmate\models\ValidationSettings;
use vaersaagod\assetmate\models\VolumeSettings;

/**
 * Validate Service
 *
 * @author    Værsågod
 * @package   AssetMate
 * @since     1.0.0
 */
class Validate extends Component
{
    public function validateAsset(Asset $asset): void
    {
        $volume = AssetMateHelper::getAssetVolume($asset);

        if (!$volume) {
            return;
        }

        $config = self::getValidateConfig($volume);

        $extension = pathinfo(($asset->newFilename ?? $asset->filename), PATHINFO_EXTENSION);

        // validating extension
        if (!empty($config->extensions) && !\in_array(\strtolower($extension), $config->extensions, true)) {
            $asset->addError('title', Craft::t('assetmate', '{extension} is not an allowed file extension. Please upload {allowedExtensions} files only to this volume.', ['extension' => $extension, 'allowedExtensions' => $this->formatAllowed($config->extensions)]));

            return;
        }

        // validating kind
        if (!empty($config->kinds) && !\in_array(\strtolower($asset->kind), $config->kinds, true)) {
            $asset->addError('title', Craft::t('assetmate', '{kind} is not an allowed file type. Please upload {allowedKinds} files only to this volume.', ['kind' => $asset->kind, 'allowedKinds' => $this->formatAllowed($config->kinds)]));

            return;
        }

        // validating size
        if (!empty($config->size)) {
            $filePath = $asset->tempFilePath ?: $asset->getCopyOfFile();
            $fileSize = \filesize($filePath);

            if ($config->size->max) {
                $maxSize = ConfigHelper::sizeInBytes($config->size->max);

                if ($fileSize > $maxSize) {
                    $asset->addError('title', Craft::t('assetmate', 'The file exceeds the maximum upload limit of {size}.', ['size' => $config->size->max]));
                }
            }

            if ($config->size->min) {
                $minSize = ConfigHelper::sizeInBytes($config->size->min);

                if ($fileSize < $minSize) {
                    $asset->addError('title', Craft::t('assetmate', 'The file is smaller than the minimum upload limit of {size}.', ['size' => $config->size->min]));
                }
            }
        }

        // validating dimensions
        if (!empty($config->dimensions) || $config->autoValidateResizeDimensions) {
            if (empty($config->dimensions)) {
                $resizeConfig = Resize::getResizeConfig($volume);

                if (isset($resizeConfig->maxWidth) || isset($resizeConfig->maxHeight)) {
                    $config->dimensions = new ValidationDimensions(['maxWidth' => $resizeConfig->maxWidth, 'maxHeight' => $resizeConfig->maxHeight]);
                }
            }
            
            if (!empty($config->dimensions)) {
                $path = AssetMateHelper::getAssetPath($asset);

                if (!empty($path) && Image::canManipulateAsImage(@pathinfo($path, PATHINFO_EXTENSION))) {
                    try {
                        $image = \Craft::$app->images->loadImage($path);
                        $imageWidth = $image->getWidth();
                        $imageHeight = $image->getHeight();
                        
                        if (isset($config->dimensions->maxWidth) && $imageWidth > $config->dimensions->maxWidth) {
                            $asset->addError('title', Craft::t('assetmate', 'The width is larger than {maxWidth}px.', ['maxWidth' => $config->dimensions->maxWidth]));
                        }
                        if (isset($config->dimensions->maxHeight) && $imageHeight > $config->dimensions->maxHeight) {
                            $asset->addError('title', Craft::t('assetmate', 'The height is larger than {maxHeight}px.', ['maxHeight' => $config->dimensions->maxHeight]));
                        }
                        if (isset($config->dimensions->minWidth) && $imageWidth < $config->dimensions->minWidth) {
                            $asset->addError('title', Craft::t('assetmate', 'The width is smaller than {minWidth}px.', ['minWidth' => $config->dimensions->minWidth]));
                        }
                        if (isset($config->dimensions->minHeight) && $imageHeight < $config->dimensions->minHeight) {
                            $asset->addError('title', Craft::t('assetmate', 'The height is smaller than {minHeight}px.', ['minHeight' => $config->dimensions->minHeight]));
                        }
                    } catch (\Throwable $throwable) {
                        \Craft::error('An error occured when trying to open Asset to check dimensions: '.$throwable->getMessage(), __METHOD__);
                    }
                }
            }
        }
    }

    /**
     * @param string|Volume $volume Volume model or handle
     *
     * @return ValidationSettings
     */
    public static function getValidateConfig(string|Volume $volume): ValidationSettings
    {
        if ($volume instanceof Volume) {
            $volume = $volume->handle;
        }

        /** @var Settings $config */
        $config = AssetMate::$plugin->getSettings();
        $volumes = $config->volumes;

        $defaultConfig = new VolumeSettings($volumes['*'] ?? []);
        $volumeConfig = new VolumeSettings($volumes[$volume] ?? []);

        $r = array_merge($defaultConfig->validation, $volumeConfig->validation);

        return new ValidationSettings($r);
    }

    private function formatAllowed(array $list): string
    {
        if (count($list) < 2) {
            return Craft::t('assetmate', $list[0]);
        }

        $list = array_map(static function($el) {
            return Craft::t('assetmate', $el);
        }, $list);

        return implode(', ', array_slice($list, 0, -1)).' '.Craft::t('assetmate', 'or').' '.end($list);
    }
}
