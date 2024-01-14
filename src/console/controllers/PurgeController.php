<?php

namespace vaersaagod\assetmate\console\controllers;

use Craft;
use craft\console\Controller;
use craft\db\Query;
use craft\db\Table;
use craft\elements\Asset;
use craft\helpers\Assets;
use craft\helpers\ConfigHelper;
use craft\helpers\Db;
use craft\helpers\Json;
use craft\models\Volume;

use vaersaagod\assetmate\helpers\ContentTablesHelper;

use yii\console\ExitCode;
use yii\helpers\BaseConsole;

/**
 * Purge controller
 */
class PurgeController extends Controller
{

    /** @var string */
    public $defaultAction = 'assets';

    /** @var string Set to a volume handle to only purge unused assets and/or delete empty folders in that volume */
    public string $volume = '*';

    /** @var string Set to a valid file kind (e.g. "image" or "video") to only purge unused assets with that kind */
    public string $kind = '*';

    /** @var int|string|bool The minimum amount of time (in seconds) passed since assets' dateUpdated timestamp. Assets updated later than this value will not be purged. Can be set to a valid DateInterval string, or false to disable the dateUpdated check. */
    public int|string|bool $lastUpdatedBefore = 'P30D';

    /** @var bool Whether to search content tables for non-relation asset uses (e.g. reference tags in rich text fields). Takes a long time if there are a lot of assets and/or content. */
    public bool $searchContentTables = true;

    /** @var bool Whether to automatically purge empty folders after purging unused assets. */
    public bool $purgeEmptyFolders = true;

    public function options($actionID): array
    {
        $options = parent::options($actionID);
        switch ($actionID) {
            case 'assets':
                $options[] = 'volume';
                $options[] = 'kind';
                $options[] = 'lastUpdatedBefore';
                $options[] = 'searchContentTables';
                $options[] = 'purgeEmptyFolders';
                break;
            case 'folders':
                $options[] = 'volume';
                break;
        }
        return $options;
    }

    /**
     * Purges unused assets. An "unused" asset is an asset that is not the target of any element relations, and is not referenced in content table text columns (e.g. in reference tags etc).
     *
     * assetmate/purge command
     */
    public function actionAssets(): int
    {

        $then = time();

        $query = (new Query())
            ->select('assets.id')
            ->from(Table::ASSETS . ' AS assets')
            ->innerJoin(Table::ELEMENTS . ' AS elements', 'assets.id = elements.id AND elements.dateDeleted IS NULL') // Excluding assets that are already soft-deleted
            ->where(['NOT EXISTS', (new Query())
                ->from(Table::USERS . ' AS users')
                ->where('assets.id = users.photoId')
            ]);

        if ($this->volume !== '*') {
            $volume = $this->_getVolumeFromHandle($this->volume);
            if (!$volume) {
                return ExitCode::UNSPECIFIED_ERROR;
            }

            $query
                ->andWhere('assets.volumeId = :volumeId', [
                    ':volumeId' => $volume->id,
                ]);
        }

        if ($this->kind !== '*') {
            $allFileKinds = array_keys(Assets::getFileKinds());
            if (!in_array($this->kind, $allFileKinds)) {
                $this->stderr("Unknown file kind \"$this->kind\". Valid file kinds are \n" . implode("\n", $allFileKinds) . PHP_EOL, BaseConsole::FG_RED);
                return ExitCode::UNSPECIFIED_ERROR;
            }

            $query
                ->andWhere('assets.kind = :kind', [
                    ':kind' => $this->kind,
                ]);
        }

        if ($this->lastUpdatedBefore) {
            $lastUpdatedBeforeSeconds = ConfigHelper::durationInSeconds($this->lastUpdatedBefore);
            $lastUpdatedBeforeDateTime = (new \DateTime())->modify("-$lastUpdatedBeforeSeconds seconds");
            $query
                ->andWhere('assets.dateUpdated < :dateTime', [
                    ':dateTime' => Db::prepareDateForDb($lastUpdatedBeforeDateTime),
                ]);
        }

        $totalAssetsCount = $query->count();

        $this->stdout(PHP_EOL);
        $this->stdout("Evaluating {$totalAssetsCount} total assets...\n", BaseConsole::FG_CYAN);
        $this->stdout(PHP_EOL);

        $query
            ->andWhere(['NOT IN', 'assets.id', (new Query())
                ->select('targetId')
                ->from(Table::RELATIONS)
            ]);

        $assetsWithoutRelationsCount = $query->count();

        if (!$assetsWithoutRelationsCount) {
            $this->stdout("No unused assets found 🎉" . PHP_EOL, BaseConsole::FG_PURPLE);
            return ExitCode::OK;
        }

        $this->stdout("Found {$assetsWithoutRelationsCount} assets that aren't targeted in any element relations.\n", BaseConsole::FG_PURPLE);

        if ($this->searchContentTables) {

            $this->stdout(PHP_EOL);
            $this->stdout("Searching for asset references in content tables... 🔍" . PHP_EOL, BaseConsole::FG_YELLOW);

            $contentTableAssetIds = [];
            $contentTablesToSearch = ContentTablesHelper::getTextColumnsByTable();

            if (!empty(array_keys($contentTablesToSearch))) {

                $this->stdout("Content tables being searched:\n", BaseConsole::FG_YELLOW);

                foreach ($contentTablesToSearch as $table => ['count' => $rowCount]) {
                    $this->stdout("$table ($rowCount rows):\n", BaseConsole::FG_YELLOW);
                }

                $i = 0;
                BaseConsole::startProgress(0, $query->count());

                foreach ($query->batch(500) as $unrelatedAssetsBatch) {
                    $assetIds = array_diff(array_column($unrelatedAssetsBatch, 'id'), $contentTableAssetIds);
                    if (empty($assetIds)) {
                        continue;
                    }
                    $referenceTagPattern = implode('|', array_map(static fn(int $assetId) => "\{asset:$assetId:", $assetIds));
                    $implodedAssetIdsArray = "'" . implode("','", $assetIds) . "'";
                    foreach ($contentTablesToSearch as $table => ['columns' => $columns]) {
                        $referenceTagsQuery = (new Query())
                            ->select($columns)
                            ->from($table);
                        foreach ($columns as $column) {
                            $referenceTagsQuery
                                ->orWhere(['REGEXP', $column, $referenceTagPattern])
                                ->orWhere("JSON_VALID($column) AND JSON_EXTRACT($column, '$.type') = :type AND JSON_EXTRACT($column, '$.value') IN (" . $implodedAssetIdsArray . ")", [
                                    ':type' => 'asset',
                                ]);
                        }
                        $contentTableAssetIds = [
                            ...$contentTableAssetIds,
                            ...$this->_getAssetIdsFromContentTableRows($referenceTagsQuery->all()),
                        ];
                    }
                    $contentTableAssetIds = array_keys(array_flip($contentTableAssetIds)); // Drop duplicate IDs
                    $i += count($assetIds);
                    $numFound = count($contentTableAssetIds);
                    BaseConsole::updateProgress($i, $query->count(), "Searching content tables... ($numFound found) ");
                }

                BaseConsole::endProgress(true);

                $time = time() - $then;
                $this->stdout(PHP_EOL);
                $this->stdout("Done ({$time}s)! 🎉" . PHP_EOL, BaseConsole::FG_PURPLE);
                $this->stdout(PHP_EOL);

                $countReferenceTags = count($contentTableAssetIds);
                if ($countReferenceTags) {
                    $this->stdout("Found $countReferenceTags assets referenced in content tables; these will not be purged.\n", BaseConsole::FG_PURPLE);
                    $query
                        ->andWhere(['NOT IN', 'assets.id', $contentTableAssetIds]);
                } else {
                    $this->stdout("No referenced assets found in content tables.\n", BaseConsole::FG_PURPLE);
                }

            } else {

                $this->stdout(PHP_EOL);
                $this->stdout("There are no content tables to search.\n", BaseConsole::FG_PURPLE);

            }

        } else {

            $this->stdout(PHP_EOL);
            $this->stdout("Warning: content tables are not being searched for used assets.\n", BaseConsole::FG_RED);

        }

        $this->stdout(PHP_EOL);

        $unusedAssetsCount = $query->count();

        if (!$unusedAssetsCount) {
            $this->stdout("No unused assets found.\n", BaseConsole::FG_PURPLE);
            return ExitCode::OK;
        }

        $totalFileSize = (clone $query)
            ->select('SUM(assets.size) AS size')
            ->scalar();
        $totalFileSize = Craft::$app->getFormatter()->asShortSize($totalFileSize, 2);

        $this->stdout("Found $unusedAssetsCount assets ($totalFileSize total) that appear to be unused.\n", BaseConsole::FG_PURPLE);
        $this->stdout(PHP_EOL);

        if ($this->interactive && !$this->confirm("Delete {$unusedAssetsCount} assets? Both the files and their asset records will permanently deleted 🔥" . PHP_EOL)) {
            $this->stdout(PHP_EOL);
            $this->stdout("OK, no harm done!\n", BaseConsole::FG_YELLOW);

        } else {
            $this->stdout(PHP_EOL);

            $then = time();

            $i = 0;
            BaseConsole::startProgress(0, $unusedAssetsCount, 'Deleting assets... ');

            $numDeleted = 0;
            $numErrors = 0;
            foreach ($query->each() as ['id' => $assetId]) {
                try {
                    if (!$this->_deleteAsset($assetId)) {
                        throw new \Exception("Unable to delete asset ID {$assetId}");
                    }
                    $numDeleted++;
                } catch (\Throwable $e) {
                    Craft::error($e, __METHOD__);
                    $this->stderr($e->getMessage() . PHP_EOL, BaseConsole::FG_RED);
                    $numErrors++;
                }
                $i++;
                BaseConsole::updateProgress($i, $unusedAssetsCount, 'Deleting assets...');
            }

            BaseConsole::endProgress(true);

            $time = time() - $then;
            $this->stdout("Done ({$time}s)! 🎉" . PHP_EOL, BaseConsole::FG_PURPLE);
            $this->stdout(PHP_EOL);
            $this->stdout("$numDeleted of $unusedAssetsCount assets were deleted.\n", BaseConsole::FG_PURPLE);
            $this->stdout("There were $numErrors errors.\n", BaseConsole::FG_PURPLE);

            // Purge empty folders?
            if ($this->purgeEmptyFolders) {
                $this->stdout(PHP_EOL);
                $this->stdout("Searching for empty folders to purge...\n", BaseConsole::FG_YELLOW);
                $this->interactive = false;
                if ($this->actionFolders() === ExitCode::OK) {
                    $this->stdout("Done! 🎉" . PHP_EOL, BaseConsole::FG_PURPLE);
                } else {
                    $this->stderr("Failed to purge empty folders\n", BaseConsole::FG_RED);
                }
            }

        }

        return ExitCode::OK;
    }

    /**
     * Purges empty folders.
     *
     * assetmate/folders command
     */
    public function actionFolders(): int
    {

        $query = (new Query())
            ->select(['folders.id', 'folders.parentId'])
            ->from(Table::VOLUMEFOLDERS . ' AS folders')
            ->where(['!=', 'folders.parentId', 'NULL']) // Folders without parent IDs are root folders
            ->andWhere(['!=', 'folders.path', 'NULL']); // Folders without paths could be root folder, temporary uploads, user photos

        if ($this->volume != '*') {
            $volume = $this->_getVolumeFromHandle($this->volume);
            if (!$volume) {
                return ExitCode::UNSPECIFIED_ERROR;
            }
            $query
                ->andWhere('folders.volumeId = :volumeId', [
                    ':volumeId' => $volume->id,
                ]);
        } else {
            $query
                ->andWhere(['!=', 'folders.volumeId', 'NULL']); // Folders without a volume ID is typically user photos
        }

        $query
            ->andWhere(['NOT EXISTS', (new Query())
                ->from(Table::ASSETS . ' AS assets')
                ->where('folders.id = assets.folderId')
            ]);

        $totalFoldersCount = $query->count();

        $i = 0;
        BaseConsole::startProgress(0, $totalFoldersCount);

        $foldersToDeleteIds = [];
        foreach ($query->each() as ['id' => $folderId]) {
            $isEmpty = !Asset::find()
                ->folderId($folderId)
                ->status(null)
                ->includeSubfolders()
                ->exists();
            if ($isEmpty) {
                $foldersToDeleteIds[] = $folderId;
            }
            $i++;
            BaseConsole::updateProgress($i, $totalFoldersCount, 'Scanning for empty folders...');
        }

        BaseConsole::endProgress(true);

        $totalFoldersCount = count($foldersToDeleteIds);

        if (!$totalFoldersCount) {
            $this->stdout("No empty folders found.\n", BaseConsole::FG_PURPLE);
            return ExitCode::OK;
        }

        $this->stdout(PHP_EOL);

        if ($this->interactive && !$this->confirm("Proceed to delete $totalFoldersCount empty folders? This action cannot be undone 🔥")) {
            $this->stdout(PHP_EOL);
            $this->stdout("OK, your folders live to see another day.\n", BaseConsole::FG_PURPLE);
            return ExitCode::OK;
        }

        $i = 0;
        BaseConsole::startProgress(0, $totalFoldersCount);

        $numDeleted = 0;
        $numErrors = 0;
        foreach ($foldersToDeleteIds as $folderId) {
            if (!Craft::$app->getAssets()->getFolderById($folderId)) {
                $numDeleted++;
            } else {
                try {
                    Craft::$app->getAssets()->deleteFoldersByIds([$folderId]);
                    $numDeleted++;
                } catch (\Throwable $e) {
                    Craft::error($e, __METHOD__);
                    $numErrors++;
                }
            }
            $i++;
            BaseConsole::updateProgress($i, $totalFoldersCount, 'Deleting folders...');
        }

        BaseConsole::endProgress(true);

        $this->stdout("Done! 🎉" . PHP_EOL, BaseConsole::FG_PURPLE);
        $this->stdout(PHP_EOL);

        $this->stdout("$numDeleted empty folders were deleted.\n", BaseConsole::FG_PURPLE);
        $this->stdout("There were $numErrors errors.\n", BaseConsole::FG_PURPLE);

        return ExitCode::OK;
    }

    private function _getAssetIdsFromContentTableRows(array $rows): array
    {
        $assetIds = [];
        foreach ($rows as $row) {
            $columns = array_filter(array_values($row));
            foreach ($columns as $column) {
                if (!is_string($column)) {
                    continue;
                }
                if (Json::isJsonObject($column)) {
                    // Assume LinkMate field
                    $json = Json::decode($column);
                    if (($json['type'] ?? null) === 'asset' && $assetId = (int)($json['value'] ?? null)) {
                        $assetIds[] = $assetId;
                    }
                } else {
                    // Get asset IDs from reference tags
                    preg_match_all('/{asset:(\d+):/', implode('', array_filter(array_values($row))), $referenceTagMatches);
                    $assetIds = [...$assetIds, ...array_filter(array_map('intval', $referenceTagMatches[1] ?? []))];
                }
            }
        }
        return array_unique($assetIds);
    }

    /**
     * @param int $id
     * @return bool
     * @throws \Throwable
     */
    private function _deleteAsset(int $id): bool
    {
        return Craft::$app->getElements()->deleteElementById($id, Asset::class, null, true);
    }

    /**
     * @param string $handle
     * @return Volume|null
     */
    private function _getVolumeFromHandle(string $handle): ?Volume
    {
        if (!$volume = Craft::$app->getVolumes()->getVolumeByHandle($handle)) {
            $allVolumes = array_map(static fn (Volume $volume) => $volume->handle, Craft::$app->getVolumes()->getAllVolumes());
            $this->stderr("Volume \"$handle\" does not exist. Valid volumes are \n" . implode("\n", $allVolumes) . PHP_EOL, BaseConsole::FG_RED);
            return null;
        }
        return $volume;
    }

}
