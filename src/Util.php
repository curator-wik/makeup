<?php
namespace curator\makeup;

class Util {
  public function validateDirectory() {
    $required_files = ['application', 'package-format-version', 'version', 'prev-versions-inorder'];
    $required_dirs = ['release_trees'];

    $validation_errors = [];
    $validation_warnings = [];

    foreach ($required_files as $filename) {
      if (is_file($filename)) {
        if (! is_readable($filename)) {
          $validation_errors[] = sprintf('Required metadata file "%s" not readable.', $filename);
        } else {
          // File-specific validations
          switch($filename) {
            case 'version':
              $raw = file_get_contents('version');
              if (trim($raw) !== trim(explode("\n", $raw, 2)[0])) {
                $validation_errors[] = 'version file must contain only 1 line.';
              }
              break;

            case 'prev-versions-inorder':
              $raw = file_get_contents('prev-versions-inorder');
              if (empty(trim($raw))) {
                $validation_errors[] = 'At least one prior release must be supplied in the prev-versions-inorder file.';
              }
              break;
          }
        }
      } else {
        $validation_errors[] = sprintf('Missing required metadata file: %s.', $filename);
      }
    }


    foreach ($required_dirs as $dir) {
      if (is_dir($dir)) {
        if (! opendir($dir)) {
          $validation_errors = sprintf('Unable to open directory "$s".', $dir);
        } else {
          closedir($dir);
        }
      } else {
        $validation_errors[] = sprintf('Missing required directory: %s.', $dir);
      }
    }

    if (empty($validation_errors)) {
      // Make sure release_trees contains subdirectories for each release stated
      // in metadata.
      $expected_releases = clone $this->getReleaseList();
      $release_iterator = new \DirectoryIterator('release_trees');
      foreach ($release_iterator as $file_info) {
        if (! $file_info->isDot()) {
          if (! $file_info->isDir()) {
            $validation_warnings[] = sprintf('WARNING: Ignoring non-directory inode release_trees/%s', $file_info->getFilename());
          } else {
            if (($ix = array_search($file_info->getFilename(), $expected_releases)) !== FALSE) {
              unset($expected_releases[$ix]);
            } else {
              $validation_errors[] = sprintf('No version order metadata for release_trees/%s.', $file_info->getFilename());
            }
          }
        }
      }
      if (count($expected_releases)) {
        $validation_errors[] = sprintf('Missing release_trees/ subdirectory for %s', implode(", ", $expected_releases));
      }
    }

    if (count($validation_warnings)) {
      fwrite(STDERR, implode("\n", $validation_warnings) . "\n");
    }

    if (count($validation_errors)) {
      fwrite(STDERR, "\n *** This directory is not a valid update package source ***\n");
      fwrite(STDERR, implode("\n", $validation_errors) . "\n");
      exit(1);
    }
  }

  public function getReleaseList() {
    static $cache = NULL;
    if ($cache == NULL) {
      $cache = array_map('trim', explode("\n", file_get_contents('prev-versions-inorder')));
      $cache[] = trim(file_get_contents('version'));
    }
    return $cache;
  }
}
