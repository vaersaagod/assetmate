<?php

namespace vaersaagod\assetmate\helpers;

use Craft;
use craft\db\Query;
use craft\db\Table;
use craft\elements\Asset;

use Illuminate\Support\Collection;

final class CpHelper
{

    public static function renderAssetMetaDataHtml(Asset $asset): string
    {

        $sourceElementIds = self::_getSourceElementIds($asset);
        $contentTableReferences = self::_getSourceElementIdsFromContentTables($asset);

        Craft::dd($contentTableReferences);

        return '';

//
//        Craft::dd($contentTableReferences);

//        $html = <<<HTML
//        <div class="meta">
//            <h2>In use</h2>
//        </div>
//HTML;
//        return $html;
    }

    /**
     * Get IDs for all elements this asset is related to, grouped by element type
     *
     * @param Asset $asset
     * @return array
     */
    private static function _getSourceElementIds(Asset $asset): array
    {
        $elements =
            self::_getSourceElementsQuery()
            ->andWhere(['IN', 'elements.id', (new Query())
                ->select('sourceId')
                ->from(Table::RELATIONS . ' AS relations')
                ->where('relations.targetId = :assetId', [':assetId' => $asset->id])
                ->andWhere(['OR', 'relations.sourceSiteId = elementssites.siteId', 'relations.sourceSiteId IS NULL'])
            ])
            ->all();
        return Collection::make($elements)
            ->mapToGroups(static fn (array $item) => [$item['type'] => $item])
            ->all();
    }

    private static function _getSourceElementIdsFromContentTables(Asset $asset): array
    {
        $contentTables = ContentTablesHelper::getTextColumnsByTable();

        $elementIds = [];

        foreach ($contentTables as $table => ['columns' => $columns]) {
            $contentTableQuery = (new Query())
                ->select('elementId')
                ->from($table);
            foreach ($columns as $column) {
                $contentTableQuery
                    ->orWhere(['REGEXP', $column, "asset:{$asset->id}:url"])
                    ->orWhere("JSON_VALID($column) AND JSON_EXTRACT($column, '$.type') = :type AND JSON_EXTRACT($column, '$.value') = :assetId", [
                        ':type' => 'asset',
                        ':assetId' => $asset->id,
                    ]);
            }
//            $query
//                ->orWhere(['IN', 'elements.id', $contentTableQuery]);
            $elementIds = [...$elementIds, ...$contentTableQuery->column()];

//            $elementsQuery
//                ->orWhere(['IN', 'elements.id', $contentTableQuery]);
        }

        if (empty($elementIds)) {
            return [];
        }

        return self::_getSourceElementsQuery()
            ->andWhere(['IN', 'elements.id', $elementIds])
            ->all();

        //Craft::dd($ids);

        /*

        $sourceElementIds = [];

        $elementsQuery = self::_getSourceElementsQuery();

        foreach ($contentTables as $table => ['columns' => $columns]) {
            $contentTableQuery = (new Query())
                ->select('elementId')
                ->from($table);
            foreach ($columns as $column) {
                $contentTableQuery
                    ->orWhere(['REGEXP', $column, ':referenceTag'], [':referenceTag' => "\{asset:{$asset->id}:"])
                    ->orWhere("JSON_VALID($column) AND JSON_EXTRACT($column, '$.type') = :type AND JSON_EXTRACT($column, '$.value') = :assetId", [
                        ':type' => 'asset',
                        ':assetId' => $asset->id,
                    ]);
            }
            $elementsQuery
                ->orWhere(['IN', 'elements.id', $contentTableQuery]);
        }

        Craft::dd($elementsQuery->all());
        */

//        $query = (new Query())
//            ->select(['elements.id', 'elements.type'])
//            ->from(Table::ELEMENTS . ' AS elements')
//            ->where('elements.dateDeleted IS NULL')
//            ->andWhere('elements.archived IS NULL')
//
//        \Craft::dd($contentTables);
        return [];
    }

    private static function _getSourceElementsQuery(): Query
    {
        return (new Query())
            ->select(['elements.id', 'elements.canonicalId', 'elements.draftId', 'elements.revisionId', 'elements.type', 'elementssites.siteId'])
            ->from(Table::ELEMENTS . ' AS elements')
            ->where('elements.dateDeleted IS NULL')
            ->andWhere('elements.archived = 0')
            ->distinct('elements.id')
            ->innerJoin(Table::ELEMENTS_SITES . ' AS elementssites', 'elementssites.elementId = elements.id');
    }

}
