<?php
namespace curator\makeup;

class Util {
  /**
   * Finds validation errors, outputs them and exits.
   *
   * @return string[]
   *   Two-element array containing application and component name.
   */
  public function validateDirectory() {
    $required_files = ['application', 'package-format-version', 'version', 'prev-versions-inorder'];
    $required_dirs = ['release_trees'];

    $validation_errors = [];
    $validation_warnings = [];

    $application = '';
    $component = '';

    foreach ($required_files as $filename) {
      if (is_file($filename)) {
        if (! is_readable($filename)) {
          $validation_errors[] = sprintf('Required metadata file "%s" not readable.', $filename);
        } else {
          // File-specific validations
          switch($filename) {
            case 'application':
              $raw = file_get_contents('application');
              if (! $this->isOneNonemptyLine($raw)) {
                $validation_errors[] = 'application file must contain exactly 1 nonempty line.';
              } else {
                $application = trim($raw);
              }
              break;

            case 'version':
              $raw = file_get_contents('version');
              if (! $this->isOneNonemptyLine($raw)) {
                $validation_errors[] = 'version file must contain exactly 1 nonempty line.';
              }
              if (strpos($raw, '/') !== FALSE) {
                $validation_errors[] = 'The character "/" is illegal in release version identifiers.';
              }
              break;

            case 'prev-versions-inorder':
              $raw = file_get_contents('prev-versions-inorder');
              if (trim($raw) === '') {
                $validation_errors[] = 'At least one prior release must be supplied in the prev-versions-inorder file.';
              }
              if (strpos($raw, '/') !== FALSE) {
                $validation_errors[] = 'The character "/" is illegal in release version identifiers.';
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
        if (! ($dh = opendir($dir))) {
          $validation_errors = sprintf('Unable to open directory "$s".', $dir);
        } else {
          closedir($dh);
        }
      } else {
        $validation_errors[] = sprintf('Missing required directory: %s.', $dir);
      }
    }

    if (empty($validation_errors)) {
      // Make sure release_trees contains subdirectories for each release stated
      // in metadata.
      $expected_releases = $this->getReleaseList();
      $release_iterator = new \DirectoryIterator('release_trees');
      foreach ($release_iterator as $file_info) {
        if (! $file_info->isDot()) {
          if (! $file_info->isDir()) {
            $validation_warnings[] = sprintf('WARNING: Ignoring non-directory: release_trees/%s', $file_info->getFilename());
          } else {
            if (($ix = array_search($file_info->getFilename(), $expected_releases)) !== FALSE) {
              unset($expected_releases[$ix]);

              // Warn if the directory is empty
              $release_probe = new \DirectoryIterator($file_info->getPathname());
              $release_has_stuff = FALSE;
              while (! $release_has_stuff && $release_probe->valid()) {
                $inode = $release_probe->current();
                $release_probe->next();
                if (! $inode->isDot() && ! $inode->getFilename() === FALSE) {
                  $release_has_stuff = TRUE;
                }
              }
              if (! $release_has_stuff) {
                $validation_errors[] = sprintf('release_trees/%s is empty.', $file_info->getFilename());
              }
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

    if (file_exists('component')) {
      if (is_readable('component')) {
        $raw = file_get_contents('component');
        if ($this->isOneNonemptyLine($raw)) {
          $component = trim($raw);
        } else {
          $validation_errors[] = 'component file, when present, must contain exactly 1 nonempty line.';
        }
      } else {
        $validation_errors[] = 'component file is not readable.';
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

    return [$application, $component];
  }

  public function getReleaseList() {
    static $cache = NULL;
    if ($cache == NULL) {
      $cache = array_filter(
        array_map('trim', explode("\n", file_get_contents('prev-versions-inorder'))),
        function($input) { return $input !== ''; }
      );
      $cache[] = trim(file_get_contents('version'));
    }
    return $cache;
  }

  protected function isOneNonemptyLine($raw) {
    if (trim($raw) !== trim(explode("\n", $raw, 2)[0]) || trim($raw) === '') {
      return FALSE;
    } else {
      return TRUE;
    }
  }
}
