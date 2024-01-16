<?php

namespace vaersaagod\assetmate\helpers;

use Craft;
use craft\base\FieldInterface;
use craft\db\Query;
use craft\db\Table;
use craft\fields\Matrix;
use craft\helpers\Db;
use craft\helpers\ElementHelper;
use craft\htmlfield\HtmlField;

use vaersaagod\linkmate\fields\LinkField;

use verbb\supertable\fields\SuperTableField;

final class ContentTablesHelper
{

    /** @var string[] Field types to include when querying for content table text columns */
    private const TEXT_COLUMN_FIELD_TYPES = [
        HtmlField::class,
        LinkField::class,
    ];

    /** @var array */
    private static array $_textColumnsByTable;

    /**
     * @return array
     * @throws \Exception
     */
    public static function getTextColumnsByTable(): array
    {
        // Get all textual field columns and search them for reference tags
        foreach (Craft::$app->getFields()->getAllFields() as $field) {
            self::_addTextColumnsForField($field);
        }

        // Exclude content tables with no relevant rows
        $ret = [];
        foreach (self::$_textColumnsByTable as $table => $columns) {
            $rowCountQuery = (new Query())
                ->from($table);
            foreach ($columns as ['column' => $column]) {
                $rowCountQuery
                    ->orWhere("$column IS NOT NULL");
            }
            $rowCount = $rowCountQuery->count();
            if (!$rowCount) {
                continue;
            }
            $ret[$table] = [
                'columns' => $columns,
                'count' => $rowCount,
            ];
        }

        return $ret;
    }

    /**
     * @param FieldInterface $field
     * @return void
     * @throws \Exception
     */
    private static function _addTextColumnsForField(FieldInterface $field): void
    {
        if ($field instanceof Matrix) {
            self::_addTextColumnsForMatrixField($field);
        } else if ($field instanceof SuperTableField) {
            self::_addTextColumnsForSuperTableField($field);
        } else {
            self::_addTextColumnsForContentTableField($field);
        }
    }

    /**
     * @param Matrix $matrixField
     * @return void
     * @throws \Exception
     */
    private static function _addTextColumnsForMatrixField(Matrix $matrixField): void
    {
        $blockTypes = Craft::$app->getMatrix()->getBlockTypesByFieldId($matrixField->id);

        foreach ($blockTypes as $blockType) {
            $fieldColumnPrefix = 'field_' . $blockType->handle . '_';

            foreach ($blockType->getCustomFields() as $field) {
                self::_addTextColumnsForContentTableField($field, $matrixField->contentTable, $fieldColumnPrefix);
            }
        }
    }

    /**
     * @param SuperTableField $superTableField
     * @return void
     * @throws \Exception
     */
    private static function _addTextColumnsForSuperTableField(SuperTableField $superTableField): void
    {
        $fields = $superTableField->getBlockTypeFields();

        foreach ($fields as $field) {
            self::_addTextColumnsForContentTableField($field, $superTableField->contentTable);
        }
    }

    /**
     * @param FieldInterface $field
     * @param string $table
     * @param string $fieldColumnPrefix
     * @return void
     * @throws \Exception
     */
    private static function _addTextColumnsForContentTableField(FieldInterface $field, string $table = Table::CONTENT, string $fieldColumnPrefix = 'field_'): void
    {

        if (!$field::hasContentColumn() || !self::_isSupportedTextField($field)) {
            return;
        }

        $columnType = $field->getContentColumnType();

        if (is_array($columnType)) {
            foreach (array_keys($columnType) as $i => $key) {
                if (Db::isTextualColumnType($columnType[$key])) {
                    $column = ElementHelper::fieldColumn($fieldColumnPrefix, $field->handle, $field->columnSuffix, $i !== 0 ? $key : null);
                    self::$_textColumnsByTable[$table][] = [
                        'column' => $column,
                        'type' => $field::class,
                    ];
                }
            }
        } elseif (Db::isTextualColumnType($columnType)) {
            $column = ElementHelper::fieldColumn($fieldColumnPrefix, $field->handle, $field->columnSuffix);
            self::$_textColumnsByTable[$table][] = [
                'column' => $column,
                'type' => $field::class,
            ];
        }
    }

    /**
     * @param FieldInterface $field
     * @return bool
     * @throws \Exception
     */
    private static function _isSupportedTextField(FieldInterface $field): bool
    {
        if (!class_exists($field::class)) {
            throw new \Exception("The field class " . $field::class . " does not exist. Maybe a missing plugin?");
        }
        foreach (self::TEXT_COLUMN_FIELD_TYPES as $fieldType) {
            if ($field::class === $fieldType || $field instanceof $fieldType) {
                return true;
            }
        }
        return false;
    }

}
