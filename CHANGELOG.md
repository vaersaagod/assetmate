# AssetMate Changelog

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
