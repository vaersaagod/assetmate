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
            ]
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


---

## Price, license and support

The plugin is released under the MIT license. It's made for Værsågod and friends, and no support 
is given. Submitted issues are resolved if it scratches an itch. 

## Changelog

See [CHANGELOG.MD](https://raw.githubusercontent.com/vaersaagod/AssetMate/master/CHANGELOG.md).

## Credits

Brought to you by [Værsågod](https://www.vaersaagod.no)

Icon designed by [Freepik from Flaticon](https://www.flaticon.com/authors/freepik).
