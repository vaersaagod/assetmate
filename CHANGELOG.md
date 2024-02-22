# AssetMate Changelog

## 2.5.0 - 2024-02-22
### Added
- Added support for validating image dimensions

## 2.4.1 - 2024-01-16
### Added
- Added the `--searchContentTablesBatchSize` option to the `assetmate/purge` CLI command
### Changed
- AssetMate no longer searches *every* textual column across all content tables when purging unused assets; only HTML fields (Redactor/CK Editor) and LinkMate are searched.
- After purging unused assets, the `assetmate/purge` CLI command will now prompt the user for confirmation before scanning for and deleting empty folders
### Improved
- Improved performance when purging unused assets via the `assetmate/purge` CLI command
- The `assetmate/purge` CLI command will now throw an exception if a field type being searched in content tables is missing its class. This prevents skipping over content table text columns that could contain references to "unused" assets.  
### Fixed
- Fixed a PDO exception that could occur when hitting the `regexp_time_limit` setting on MySQL 8, when doing regex matching on content table text columns when purging unused assets  
- Fixed a PDO exception that could occur when doing regex matching on content table text columns when purging unused assets  
- Fixed a PHP exception that could occur if the `--lastUpdatedBefore` option for the `assetmate/purge` command was set to `true`  
- Fixed an issue where the `assetmate/purge` CLI command would purge assets in reference tags if the reference tag contained a site ID (i.e. `@1` or similar)  

## 2.4.0 - 2024-01-15
### Added
- Added the `assetmate/purge` CLI command for bulk-deleting unused assets
- Added the `assetmate/purge/folders` CLI command for bulk-deleting empty folders

## 2.3.2 - 2023-09-28
### Fixed
- Fixed an issue where AssetMate could cause a type related PHP exception for assets without a volume (temporary files)  

## 2.3.1 - 2023-06-10
### Fixed
- Fixed an issue where AssetMate could cause a type related PHP exception when uploading files  

## 2.3.0 - 2023-06-02

### Added
- Added support for setting quality per resize config
- Added support for converting file formats on upload

## 2.2.0 - 2023-05-10

### Fixed
- Fixed a PHP exception that would be thrown when uploading assets directly to Assets fields

### Changed 
- AssetMate now logs errors and warnings to its own log target `assetmate`
- AssetMate now resizes assets when moving them between volumes, if the target volume has a `maxWidth` and `maxHeight` set in its resize rules
- AssetMate now requires Craft 4.3.11 or later

### Added
- Added `AssetMateHelper`  

## 2.1.1 - 2022-06-01

### Fixed
- Fixed validation for propagating elements

## 2.1.0 - 2022-05-18

### Added
- Added image resizing on upload

## 2.0.0 - 2022-05-11

### Added
- Added Craft 4 support

## 1.0.0 - 2022-03-15

### Added
- Initial public release
