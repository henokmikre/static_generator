# Drupal Static Generator

A static page generator for Drupal

- [Static Generator](#drupal-admin-ui)
  * [Installation](#installation)
    + [Requirements](#requirements)
    + [Steps](#steps)
  * [Settings](#settings)
  * [Generation](#generation)
    + [Overview](#overview)
    + [Full Generation](#full-generation)
    + [Page Generation](#page-generation)
    + [Block Generation](#block-generation)
    + [Redirect Generation](#redirect-generation)
    + [Files Generation](#files-generation)
    
## Installation
- Install this module using the normal Drupal module installation process.
- Configure a private directory in settings.php.
- Configure static generation settings at /admin/config/system/static_generator.

### Requirements

- Drupal 8.5 or greater
- PHP 7.2 or greater

## Settings
 
- The settings page is located at: /admin/config/system/static_generator.
- Generator Directory: Determines where the generated files are placed. The default setting is to generate files in the
  private files directory.  To generate files in a directory that is within the private files directory,
  specify that directory in the setting, e.g. private://<generator_directory>.
- rSync Public: The rSync command line options for public file generation.
- rSync Code: The rSync command line options for code file generation.
- Paths to generate: Specify which paths to generate.  If left blank, all paths are generated.
- Paths to not generate: Specify which paths to never generate.
- Blocks to ESI: Specify which blocks to ESI include. If left blank, all blocks are ESI included.
- Blocks to not ESI: Specify blocks that should not be ESI included.
- Frequently changing blocks: Specify blocks that change frequently.
- Entity Types: Specify which entity types to generate.

## Generation
## Overview
The Static Generator module renders each page and then creates a file for the page
in the appropriate directory within the static generation directory, which is specified
in the settings. The pages are rendered for the Annonymous user.  Only published pages 
are generated.

### Full Site Generation
To generate the entire site, including pages, ESI's, and files:
```
drupal sg
```
### Page Generation

To generate all pages:
```
drupal sgp
```

To generate a specific page:

```
drupal sgp '/node/123'
```

To generate a test sample of a specified number of pages (e.g. 50 pages)
```
drupal sgp '' '50'
```

### Blocks
To generate all blocks:
```
drupal sgb
```

To generate a specific block:

```
drupal sgp 'block_id'
```

To generate frequently changing blocks:

```
drupal sgp --frequent
```

### Redirects
To generate redirects (requires the redirect module be installed):
```
drupal sgr
```

### Files

To generate all files:
```
drupal sgf
```
To generate public files:
```
drupal sgf -- public
```
To generate private files:
```
drupal sgf -- private
```

### Deleting
To delete all generated files:
```
drupal sgd
```
To delete all generated pages:
```
drupal sgd -- pages
```

### Workflow Integration