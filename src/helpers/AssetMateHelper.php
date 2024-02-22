<?php

namespace vaersaagod\assetmate\helpers;

use craft\elements\Asset;
use craft\errors\VolumeException;
use craft\fields\Assets as AssetsField;
use craft\fs\Temp;
use craft\helpers\Assets;
use craft\models\Volume;

use yii\base\InvalidArgumentException;
use yii\base\InvalidConfigException;

class AssetMateHelper
{

    /**
     * Get the Volume for an asset, taking `newLocation` and temporary uploads into account
     *
     * @param Asset $asset
     * @return Volume|null
     */
    public static function getAssetVolume(Asset $asset): ?Volume
    {
        try {
            // We'll use the asset's folder ID to determine its (eventual) volume
            if (isset($asset->newLocation)) {
                [$folderId] = Assets::parseFileLocation($asset->newLocation);
            } else {
                $folderId = $asset->newFolderId ?? $asset->folderId;
            }
            if (!$folderId) {
                return null;
            }
            $volume = \Craft::$app->getAssets()->getFolderById($folderId)?->getVolume();
            if (!$volume) {
                return null;
            }
            // If the volume belongs to a temporary FS, this most likely means that the file is being uploaded directly to an Assets field
            // ...in which case there should be a `fieldId` request parameter, and we can use that to figure out what the *actual* volume is going to be, in the end
            if (
                $volume->getFs() instanceof Temp &&
                (!\Craft::$app->getRequest()->getIsConsoleRequest() && $fieldId = (int)\Craft::$app->getRequest()->getParam('fieldId'))
            ) {
                $field = \Craft::$app->getFields()->getFieldById($fieldId);
                if (!$field instanceof AssetsField) {
                    return null;
                }
                $folderId = $field->resolveDynamicPathToFolderId($asset);
                $volume = \Craft::$app->getAssets()->getFolderById($folderId)?->getVolume();
            }
        } catch (InvalidArgumentException|InvalidConfigException) {
            return null;
        }
        return $volume;
    }

    public static function getAssetPath(Asset $asset): ?string
    {
        $volume = self::getAssetVolume($asset);
        $path = $asset->tempFilePath;

        // In case the asset is being moved, there won't be a `tempFilePath` to (maybe) resize
        // We work around this by pulling a copy of the file to a local, temporary file path
        // We only do this if the asset is actually moved to a new volume, though.
        if (
            !$path &&
            $volume?->id !== $asset->volumeId &&
            ($asset->getScenario() === Asset::SCENARIO_MOVE || $asset->getScenario() === Asset::SCENARIO_FILEOPS)
        ) {
            try {
                $path = $asset->getCopyOfFile();
                $asset->tempFilePath = $path;
            } catch (VolumeException|InvalidConfigException) {
                return null;
            }
        }
        
        return $path;
    }
}
