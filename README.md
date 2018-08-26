# "Makeup"
Makes [cpkg](https://github.com/curator-wik/common-docs/blob/master/update_package_structure.md) update
packages given 
  * some simple metadata files (`application`, `version`, etc), and
  * pristine source trees for each release to be included in the update package.

Essentially, this project precomputes the differences between an "old" and "new" directory tree
and records what it found as a cpkg. Cpkgs are downloadable artifact Curator can replay
to recreate the "new" directory tree given the "old" directory tree.

## Usage
  1. Create a new empty directory
  2. Within it, populate the metadata files according to the cpkg documentation.  
     Example input metadata files: https://github.com/curator-wik/drupal_cpkg_source
  3. Within it, create a directory called `release_trees`.
  4. Place unmodified releases of the application under release_trees, one for
     each version you included in the `version` and `prev_versions_inorder` file.
     Each release should be rooted at a directory whose name matches the version
     as it appears in these files.
  5. Run `php src/makeup.php -d /path/to/directory/from/step/1`. Your .zip file will be written to this directory.

## Known issues
  * This script produces a `____.cpkg.zip` file whose exact name is a function of the metadata files.
    However, some characters in the filename seem to break the `\PharData` class that Curator currently
    uses to process the update package. You may need to rename the output file; letters, dashes and
    numbers are known to be safe.
    (When you get this wrong, `\PharData` throws an inaccurate exception about the extension being unrecognized.)
