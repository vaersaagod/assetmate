<?php

namespace vaersaagod\assetmate\services;

use Craft;
use craft\base\Component;
use craft\elements\Asset;
use craft\helpers\ConfigHelper;

use vaersaagod\assetmate\AssetMate;
use vaersaagod\assetmate\models\Settings;
use vaersaagod\assetmate\models\ValidationSettings;
use vaersaagod\assetmate\models\VolumeSettings;

use yii\base\InvalidConfigException;

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
        try {
            $volume = $asset->getVolume()->handle;
        } catch (InvalidConfigException $e) {
            return;
        }

        if (empty($volume)) {
            return;
        }

        $config = $this->getValidateConfig($volume);
        $extension = pathinfo(($asset->newFilename ?? $asset->filename), PATHINFO_EXTENSION);

        if (!empty($config->extensions) && !\in_array(\strtolower($extension), $config->extensions, true)) {
            $asset->addError('title', Craft::t('assetmate', '{extension} is not an allowed file extension. Please upload {allowedExtensions} files only to this volume.', ['extension' => $extension, 'allowedExtensions' => $this->formatAllowed($config->extensions)]));

            return;
        }

        if (!empty($config->kinds) && !\in_array(\strtolower($asset->kind), $config->kinds, true)) {
            $asset->addError('title', Craft::t('assetmate', '{kind} is not an allowed file type. Please upload {allowedKinds} files only to this volume.', ['kind' => $asset->kind, 'allowedKinds' => $this->formatAllowed($config->kinds)]));

            return;
        }
        
        if (!empty($config->size)) {
            $filePath = $asset->tempFilePath ?: $asset->getCopyOfFile();
            $fileSize = \filesize($filePath);
            
            if ($config->size->max) {
                $maxSize = ConfigHelper::sizeInBytes($config->size->max);
                
                if ($fileSize > $maxSize) {
                    $asset->addError('title', Craft::t('assetmate', 'The file {file} could not be uploaded, because it exceeds the maximum upload limit of {size}.', ['file' => $asset->filename, 'size' => $config->size->max]));
                }
            }

            if ($config->size->min) {
                $minSize = ConfigHelper::sizeInBytes($config->size->min);
                
                if ($fileSize < $minSize) {
                    $asset->addError('title', Craft::t('assetmate', 'The file {file} could not be uploaded, because it is smaller than the minimum upload limit of {size}.', ['file' => $asset->filename, 'size' => $config->size->min]));
                }
            }

            return;
        }
        
        
    }

    public function getValidateConfig(string $volume): ValidationSettings
    {
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

        $list = array_map(static function ($el) {
            return Craft::t('assetmate', $el);
        }, $list);

        return implode(', ', array_slice($list, 0, -1)).' '.Craft::t('assetmate', 'or').' '.end($list);
    }
}
