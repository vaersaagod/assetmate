<?php

namespace vaersaagod\assetmate\helpers;

use Craft;
use craft\base\FieldInterface;
use craft\db\Query;
use craft\db\Table;
use craft\fields\Matrix;
use craft\helpers\Db;
use craft\helpers\ElementHelper;

use verbb\supertable\fields\SuperTableField;

final class ContentTablesHelper
{

    /** @var array */
    private static array $_textColumnsByTable;

    /**
     * @return array
     */
    public static function getTextColumnsByTable(): array
    {
        // Get all textual field columns and search them for reference tags
        foreach (Craft::$app->getFields()->getAllFields() as $field) {
            self::_addTextColumnsForField($field);
        }

        // Exclude content tables with no rows
        $ret = [];
        foreach (self::$_textColumnsByTable as $table => $columns) {
            $rowCount = (new Query())
                ->from($table)
                ->count();
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
     */
    private static function _addTextColumnsForContentTableField(FieldInterface $field, string $table = Table::CONTENT, string $fieldColumnPrefix = 'field_'): void
    {
        if (!$field::hasContentColumn()) {
            return;
        }

        $columnType = $field->getContentColumnType();

        if (is_array($columnType)) {
            foreach (array_keys($columnType) as $i => $key) {
                if (Db::isTextualColumnType($columnType[$key])) {
                    $column = ElementHelper::fieldColumn($fieldColumnPrefix, $field->handle, $field->columnSuffix, $i !== 0 ? $key : null);
                    self::$_textColumnsByTable[$table][] = $column;
                }
            }
        } elseif (Db::isTextualColumnType($columnType)) {
            $column = ElementHelper::fieldColumn($fieldColumnPrefix, $field->handle, $field->columnSuffix);
            self::$_textColumnsByTable[$table][] = $column;
        }
    }

}
