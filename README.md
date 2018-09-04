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
    
## Overview

Typically the Static Generator module is installed on a Drupal site that is located behind a firewall.
That way content editors can edit the content in a more secure environment.  As content editors 
publish content, it is pushed out to a public facing static site in real time.

### Requirements

- Drupal 8.5 or greater
- PHP 7.2 or greater
- Drupal Console

### Installation
- Install this module using the normal Drupal module installation process.
- Configure a private directory in settings.php.
- Configure static generation settings at /admin/config/system/static_generator.
- Create a script that uses rsync to push generated files to a public facing server that serves
the static files.

## Settings
 
- The settings page is located at: /admin/config/system/static_generator.
- Generator Directory: Determines where the generated files are placed. The default setting is to generate files in the
  private files directory.  To generate files in a directory that is within the private files directory,
  specify that directory in the setting, e.g. private://<generator_directory>.
- rSync Public: The rSync command line options for public file generation.
- rSync Code: The rSync command line options for code file generation.
- Paths to generate: Specify paths to generate.  This often includes paths for views that have a page display,
 or the "Default front page" setting from /admin/config/system/site-information (generated as index.html).
- Paths and Patterns to not generate: Specify which paths and/or patterns to never generate. For example, to never
generate the /about page, enter "/about", or to never generate any path starting with /about, enter "/about/*".
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


The Drupal 8 Static Generator module generates four types of files, all of which are placed in the directory specified in the setting "Generator Directory"

creates ESI includes for blocks, so that if a block is used on many pages, only the block fragment would need to be re-generated.


### Full Site Generation
To generate the entire site, including pages, ESI's, and public files and code files:
```
drupal sg
```
Note that whatever files are in the static directory are deleted first, 
except for those files or directories specified in settings as "non-Drupal".
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

## Deleting
To delete all generated pages, ESI's, public files, and code files 
(everything except files specified in settings as "non-Drupal"):
```
drupal sgd
```
To delete all generated pages:
```
drupal sgd -- pages
```
The delete all pages command will not delete those files and directories listed in the 
SG Settings "Drupal files and directories" and "Non-Drupal files and directories"

### Workflow Integration

Once the Drupal core workflow module has been installed, and workflow 
has been enabled for a specific content type at /admin/config/workflow/workflows,
page files will be automatically generated whenever a new version of 
the page is published.

### CSS/JS Aggregation

The SG Public file generation process (drupal sgf --public) replicates the aggregated CSS and JS files located in 
/sites/default/files/css and /sites/default/files/js.  The steps required for changing CSS/JS are:

1) Change the CSS/JS files (or source SCSS files etc).
2) Clear the cache (causes new aggregated CSS/JS files to be created).
3) Run "drupal sgp --public" to generate (using rsync) the public files directory, which includes the aggregated
 CSS and JS files.

### Example cron settings

0 * * * * cd /var/www/my_project/docroot && /var/www/my_project/vendor/drupal/console/bin/drupal sgb > /dev/null 2>&1

This cron entry does a full generation of all blocks at the top of each hour.  This assures that any
blocks that change are regenerated, as some may not regenerate in real time.  This is because only Custom
Blocks, which can be published using the core workflow module, are generated in real time.

*/2 * * * * cd /var/www/my_project/docroot && /var/www/my_project/vendor/drupal/console/bin/drupal sgb --frequent > /dev/null 2>&1

Generates frequently changing blocks every two minutes.  For example, if you had a block
that displayed the current temperature using a web service, then the temperature would never be more
than two minutes out of date. 

A third entry is typically required that call a custom script that pushes, using rsync, the generated static files,
to the static public facing production web server.
