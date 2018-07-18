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
- Install this module using normal Drupal module installation.
- Configure a private directory in settings.php.

### Requirements

- Drupal 8.5 or greater
- PHP 7.2 or greater

## Settings
- Generator Directory
-- Determines where the generated files are placed.
- rSync Public
-- The rSync command line options for public file generation.
- rSync Code
-- The rSync command line options for code file generation.- Paths to generate
- Paths to not generate -- Specify which paths to generate.  If left blank, all paths are generated.
- Blocks to ESI -- Specify which blocks to ESI include. If left blank, all blocks are ESI included.
- Blocks to not ESI -- Specify blocks that should not be ESI included.
- Entity Types -- Specify which entity types to generate.

## Generation
## Overview
The Static Generator module renders each page and then creates a file for the page
in the appropriate directory within the static generation directory, which is specified
in the settings. The pages are rendered for the Annonymous user.  Only published pages 
are generated.

### Full Site Generation
To generate the entire site, including pages, ESI's, and files:
```
drupal g
```
### Page Generation

To generate all pages:
```
drupal gp
```

To generate a specific page:

```
drupal gp '/node/123'
```

To generate a test sample of a specified number of pages (e.g. 50 pages)
```
drupal gp '' '50'
```

### Blocks
To generate all blocks:
```
drupal gb
```

### Redirects
To generate redirects (requires the redirect module be installed):
```
drupal gr
```

### Files

To generate all files:
```
drupal gf
```
To generate public files:
```
drupal gf -- public
```
To generate private files:
```
drupal gf -- private
```

### Deleting
To delete all generated files:
```
drupal gd
```
To delete all generated pages:
```
drupal gd -- pages
```

### Workflow Integration