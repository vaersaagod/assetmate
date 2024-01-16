AssetMate plugin for Craft CMS
===

Protect your assets, mate!
  
![Screenshot](resources/plugin_logo.png)

## Requirements

This plugin requires Craft CMS 4.0.0+ and PHP 8.0+. 

## Installation

To install the plugin, follow these instructions:

1. Install with composer via `composer require vaersaagod/assetmate` from your project directory.
2. Install the plugin in the Craft Control Panel under Settings → Plugins, or from the command line via `./craft install/plugin assetmate`.

---

## Configuring

AssetMate can be configured by creating a file named `assetmate.php` in your Craft config folder, 
and overriding settings as needed. 

```php
<?php

use craft\elements\Asset;

return [
    'volumes' => [
        '*' => [
            'validation' => [
                'size' => [
                    'max' => '20M',
                ]
            ]
        ],
        'images' => [
            'validation' => [
                'extensions' => ['gif', 'jpg', 'jpeg', 'png'],
                'size' => [
                    'max' => '2M',
                ],
            ],
            'resize' => [
                'maxWidth' => 2200,
                'maxHeight' => 2200,
                'quality' => 90,
            ],
            'convertUnmanipulable' => true,
        ],        
        'illustrations' => [
            'validation' => [
                'extensions' => ['svg'],
                'size' => [
                    'max' => '1M',
                    'min' => '50K',
                ],
            ]
        ],        
        'videos' => [
            'validation' => [
                'extensions' => ['mp4'],
                'size' => [
                    'max' => '12M',
                ],
            ],
        ],
    ]
];
```

## Console commands  

AssetMate adds a couple of asset-related CLI utility commands:  

### Purging "unused" assets  

An "unused" asset is an asset that isn't a *target* in any element relations (i.e. has no source relations), and is also not found in any reference tags in database content tables (meaning it's not linked to or used in Redactor/CKEditor/LinkMate fields).    

To delete all unused assets:  

`php craft assetmate/purge`

#### Options

##### `--volume` (string, default '*')  
Only purge assets in a specific volume, e.g. `php craft assetmate/purge --volume=images`  

##### `--kind` (string, default '*')  
Only purge assets with a specific file kind, e.g. `php craft assetmate/purge --kind=image`  

##### `--lastUpdatedBefore` (string|int|bool, default 'P30D')  
By default, AssetMate will not purge assets with a `dateUpdated` timestamp later than 30 days ago. This option can be set to an integer (i.e. number of seconds), a valid PHP date interval string, or `false` to disable the `dateUpdated` check entirely.  

##### `--searchContentTables` (bool, default `true`)  
In addition to checking the `relations` table for assets without any source element relations, AssetMate will search content tables to make sure that the assets found aren't referenced in text columns (e.g. in reference tags in Redactor or CK Editor). This can take a long time if there are a lot of assets and/or content, so if you're confident that you have no asset references in text fields, set this option to `false` to bypass content table searching altogether.  

##### `--searchContentTablesBatchSize` (int, default `500`)  
After querying for assets without any source element relations, AssetMate will search for these assets across text columns in content tables. To speed up this lengthy process, the IDs are batched. If you're running into a PDO exception "Timeout exceeded in regular expression match", consider setting the batch size to a lower value.  

##### `--deleteEmptyFolders` (bool, default `true`)  
If `true`, AssetMate will scan for and delete any empty folders after purging unused assets. If the `--volume` option is used, only empty folders in that volume will be deleted.    

### Purging empty folders  

An empty folder is a folder that doesn't contain any assets (or any sub folders that contain assets).  

To delete all empty folders:  

`php craft assetmate/purge/folders`  

#### Options  

##### `--volume` (string, default '*')
Only delete empty folders in a specific volume, e.g. `php craft assetmate/purge/folders --volume=images`  

---

## Price, license and support

The plugin is released under the MIT license. It's made for Værsågod and friends, and no support 
is given. Submitted issues are resolved if it scratches an itch. 

## Changelog

See [CHANGELOG.MD](https://raw.githubusercontent.com/vaersaagod/AssetMate/master/CHANGELOG.md).

## Credits

Brought to you by [Værsågod](https://www.vaersaagod.no)

Icon designed by [Freepik from Flaticon](https://www.flaticon.com/authors/freepik).
