<?php

namespace vaersaagod\assetmate\console\controllers;

use Craft;
use craft\base\FieldInterface;
use craft\console\Controller;
use craft\db\Query;
use craft\db\Table;
use craft\elements\Asset;
use craft\fields\Matrix;
use craft\helpers\Assets;
use craft\helpers\ConfigHelper;
use craft\helpers\Db;
use craft\helpers\ElementHelper;
use craft\helpers\Json;
use craft\models\Volume;

use craft\models\VolumeFolder;
use verbb\supertable\fields\SuperTableField;

use yii\console\ExitCode;
use yii\helpers\BaseConsole;

/**
 * Purge controller
 */
class PurgeController extends Controller
{

    /** @var string */
    public $defaultAction = 'index';

    /** @var string Set to a volume handle to only purge unused assets from that volume */
    public string $volume = '*';

    /** @var string Set to a valid file kind (e.g. "image" or "video") to only purge unused assets with that kind */
    public string $kind = '*';

    /** @var int|string|bool The minimum amount of time (in seconds) passed since assets' dateUpdated timestamp. Assets updated later than this value will not be purged. Can be set to a valid DateInterval string, or false to disable the dateUpdated check. */
    public int|string|bool $lastUpdatedBefore = 'P30D';

    /** @var bool Whether to search content tables for non-relation asset uses (e.g. reference tags in rich text fields). Takes a long time if there are a lot of assets and/or content. */
    public bool $searchContentTables = true;

    /** @var bool Whether to delete folders for purged assets, if they are empty after purging. */
    public bool $deleteFolders = true;

    /** @var array */
    private array $_textColumnsByTable;

    public function options($actionID): array
    {
        $options = parent::options($actionID);
        switch ($actionID) {
            case 'index':
                $options[] = 'volume';
                $options[] = 'kind';
                $options[] = 'lastUpdatedBefore';
                $options[] = 'searchContentTables';
                $options[] = 'deleteFolders';
                break;
        }
        return $options;
    }

    /**
     * Purges unused assets. An "unused" asset is an asset that has no relations to other elements, and is not referenced in content text columns via reference tags.
     *
     * assetmate/purge command
     */
    public function actionIndex(): int
    {

        $then = time();

        $query = (new Query())
            ->select('assets.id')
            ->from(Table::ASSETS . ' AS assets');

        if ($this->volume !== '*') {
            $volume = Craft::$app->getVolumes()->getVolumeByHandle($this->volume);
            if (!$volume) {
                $allVolumes = array_map(static fn (Volume $volume) => $volume->handle, Craft::$app->getVolumes()->getAllVolumes());
                $this->stderr("Volume \"$this->volume\" does not exist. Valid volumes are \n" . implode("\n", $allVolumes) . PHP_EOL, BaseConsole::FG_RED);
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
        $this->stdout("Evaluating {$totalAssetsCount} total assets... 🚀" . PHP_EOL, BaseConsole::FG_CYAN);

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

        $this->stdout("Found {$assetsWithoutRelationsCount} assets without a target record in the element relations table.\n", BaseConsole::FG_PURPLE);

        if ($this->searchContentTables) {

            $this->stdout(PHP_EOL);
            $this->stdout("Searching for asset references in content tables... 🔍" . PHP_EOL, BaseConsole::FG_YELLOW);

            $contentTableAssetIds = [];

            // Get all textual field columns and search them for reference tags
            foreach (Craft::$app->getFields()->getAllFields() as $field) {
                $this->_addTextColumnsForField($field);
            }

            // Exclude content tables with no rows
            $contentTablesToSearch = [];
            foreach ($this->_textColumnsByTable as $table => $columns) {
                $rowCount = (new Query())
                    ->from($table)
                    ->count();
                if (!$rowCount) {
                    continue;
                }
                $contentTablesToSearch[$table] = [
                    'columns' => $columns,
                    'count' => $rowCount,
                ];
            }

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

        $time = time() - $then;
        $this->stdout(PHP_EOL);
        $this->stdout("Done ({$time}s)! 🎉" . PHP_EOL, BaseConsole::FG_PURPLE);
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

            if ($this->deleteFolders) {
                $folderIds = (clone $query)
                    ->select('assets.folderId')
                    ->distinct()
                    ->column();
            } else {
                $folderIds = [];
            }

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

            if (!empty($folderIds)) {
                $this->stdout(PHP_EOL);
                $this->stdout("Deleting empty folders...\n");
                foreach ($folderIds as $folderId) {
                    $this->_maybeDeleteFolder($folderId);
                }
            }

            $time = time() - $then;
            $this->stdout("Done ({$time}s)! 🎉" . PHP_EOL, BaseConsole::FG_PURPLE);
            $this->stdout(PHP_EOL);
            $this->stdout("$numDeleted of $unusedAssetsCount assets were deleted.\n", BaseConsole::FG_PURPLE);
            $this->stdout("There were $numErrors errors.\n", BaseConsole::FG_PURPLE);

        }

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

    private function _addTextColumnsForField(FieldInterface $field): void
    {
        if ($field instanceof Matrix) {
            $this->_addTextColumnsForMatrixField($field);
        } else if ($field instanceof SuperTableField) {
            $this->_addTextColumnsForSuperTableField($field);
        } else {
            $this->_addTextColumnsForContentTableField($field);
        }
    }

    private function _addTextColumnsForMatrixField(Matrix $matrixField): void
    {
        $blockTypes = Craft::$app->getMatrix()->getBlockTypesByFieldId($matrixField->id);

        foreach ($blockTypes as $blockType) {
            $fieldColumnPrefix = 'field_' . $blockType->handle . '_';

            foreach ($blockType->getCustomFields() as $field) {
                $this->_addTextColumnsForContentTableField($field, $matrixField->contentTable, $fieldColumnPrefix);
            }
        }
    }

    private function _addTextColumnsForSuperTableField(SuperTableField $superTableField): void
    {
        $fields = $superTableField->getBlockTypeFields();

        foreach ($fields as $field) {
            $this->_addTextColumnsForContentTableField($field, $superTableField->contentTable);
        }
    }

    private function _addTextColumnsForContentTableField(FieldInterface $field, string $table = Table::CONTENT, string $fieldColumnPrefix = 'field_'): void
    {
        if (!$field::hasContentColumn()) {
            return;
        }

        $columnType = $field->getContentColumnType();

        if (is_array($columnType)) {
            foreach (array_keys($columnType) as $i => $key) {
                if (Db::isTextualColumnType($columnType[$key])) {
                    $column = ElementHelper::fieldColumn($fieldColumnPrefix, $field->handle, $field->columnSuffix, $i !== 0 ? $key : null);
                    $this->_textColumnsByTable[$table][] = $column;
                }
            }
        } elseif (Db::isTextualColumnType($columnType)) {
            $column = ElementHelper::fieldColumn($fieldColumnPrefix, $field->handle, $field->columnSuffix);
            $this->_textColumnsByTable[$table][] = $column;
        }
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
     * @param int $folderId
     * @return void
     */
    private function _maybeDeleteFolder(int $folderId): void
    {
        $folder = Craft::$app->getAssets()->getFolderById($folderId);
        if (!$folder) { // Folder not found
            return;
        }
        if (!$folder->parentId) { // This is a root folder. Deleting it would be bad.
            return;
        }
        $isEmpty = !Asset::find()
            ->folderId($folder->id)
            ->includeSubfolders()
            ->exists();
        if (!$isEmpty) { // This folder is not empty
            return;
        }
        try {
            Craft::$app->getAssets()->deleteFoldersByIds($folder->id);
        } catch (\Throwable $e) {
            Craft::error($e, __METHOD__);
        }
    }

}
