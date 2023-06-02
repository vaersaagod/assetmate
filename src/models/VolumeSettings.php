<?php

namespace vaersaagod\assetmate\models;

use craft\base\Model;

/**
 * @author    Værsågod
 * @package   AssetMate
 * @since     1.0.0
 */
class VolumeSettings extends Model
{
    public array $validation = [];
    public array $resize = [];
    public bool|null $convertUnmanipulable = null;

    /**
     * @return array
     */
    public function rules(): array
    {
        return [
            
        ];
    }
}
