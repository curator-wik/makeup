<?php
namespace curator\makeup;

class Util {
  public function validateDirectory() {
    $required_files = ['application', 'package-format-version', 'version', 'prev-versions-inorder'];
    $required_dirs = ['release_trees'];

    $validation_errors = [];

    foreach ($required_files as $filename) {
      if (is_file($filename)) {
        if (! is_readable($filename)) {
          $validation_errors[] = sprintf('Required metadata file "%s" not readable.', $filename);
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

    if (count($validation_errors)) {
      fwrite(STDERR, "This directory is not a valid update package source:\n");
      fwrite(STDERR, implode("\n", $validation_errors) . "\n");
      exit(1);
    }
  }
}
