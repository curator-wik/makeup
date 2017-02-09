<?php
declare(strict_types=1);
namespace curator\makeup;


use DiffMatchPatch\DiffMatchPatch;

class PackageGenerator {
  /**
   * @var \PharData $phar
   */
  protected $phar;

  public function __construct(\PharData $phar) {
    $this->phar = $phar;
  }

  public function installMetadata() {
    $copy = ['application', 'component', 'version', 'prev-versions-inorder'];
    foreach ($copy as $filename) {
      if (is_readable($filename)) {
        $this->phar->addFile($filename);
      }
    }

    $this->phar->addEmptyDir('payload');
  }

  /**
   * @param string $from_version
   * @param string $to_version
   */
  public function generatePayload(string $from_version, string $to_version) {
    $prefix = "payload/$to_version";
    $this->phar->addEmptyDir("payload/$to_version");

    $buffers = $this->processTreeDirectory($from_version, $to_version, '', []);
    if (! empty($buffers['deleted_files'])) {
      $this->phar->addFromString(
        "payload/$to_version/deleted_files",
        implode("\n", $buffers['deleted_files'])
      );
    }
  }

  /**
   * @param string $from_version
   * @param string $to_version
   * @param string $path
   *   A path that is a directory under at least one of the versions' trees.
   * @param array $buffers
   *   An associative array of data such as the contents of deleted_files which
   *   must be deferred from writing to the phar until recursion completes.
   */
  protected function processTreeDirectory(string $from_version, string $to_version, string $path, array &$buffers) {
    $on_disk_old = "release_trees/$from_version/$path";
    $on_disk_new = "release_trees/$to_version/$path";

    $old = [];
    if (is_dir($on_disk_old)) {
      $old_reader = new \FilesystemIterator($on_disk_old, \FilesystemIterator::KEY_AS_PATHNAME);
      foreach ($old_reader as $p => $file_info) {
        $old[substr($p, strlen($on_disk_old))] = $file_info;
      }
      unset($old_reader);
    }
    /*
     * If $on_disk_old is not a directory, then $on_disk_new must point to a
     * directory, because at least one of the two always has to. No matter what
     * $on_disk_old is, we want to write straight files to the archive here,
     * which is exactly what will happen with an empty $old[] array.
     */

    $new = [];
    if (is_dir($on_disk_new)) {
      $new_reader = new \FilesystemIterator($on_disk_new, \FilesystemIterator::KEY_AS_PATHNAME);
      foreach ($new_reader as $p => $file_info) {
        $new[substr($p, strlen($on_disk_new))] = $file_info;
      }
    } else if (! file_exists($on_disk_new)) {
      // It's a delete.
      $buffers['deleted_files'][] = $path;
      // We don't need to recurse down and list all descendant deleted files.
      return $buffers;
    } else if (is_file($on_disk_new)) {
      // It's a regular file replacing a directory.
      // https://github.com/curator-wik/common-docs/blob/master/update_package_structure.md
      // does not require an entry in deleted_files to overwrite the directory.
      // TODO: write under files/
      return $buffers;
    } else {
      fwrite(STDERR, sprintf("WARNING: ignoring %s because it is of unsupported type.\n", $path));
    }

    $inode_intersection = array_intersect_key($new, $old);
    /**
     * @var \SplFileInfo $new_file_info
     * @var \SplFileInfo $old_file_info
     */
    foreach ($inode_intersection as $local_path => $new_file_info) {
      $old_file_info = $old[$local_path];

      if ($new_file_info->isFile() && $old_file_info->isFile()) {
        $this->diff($old_file_info, $new_file_info, "payload/$to_version/patch_files$path");
      } else if ($new_file_info->isDir() || $old_file_info->isDir()) {
        $this->processTreeDirectory($from_version, $to_version, $path . DIRECTORY_SEPARATOR . $new_file_info->getFilename(), $buffers);
      } else {
        fwrite(STDERR, sprintf("WARNING: ignoring %s because it is of unsupported type \"%s\".\n",
          "$path/" . $new_file_info->getFilename(),
          $new_file_info->getType()
          )
        );
      }
    }

    // TODO: for each item unique to $new[], write under files/
    // TODO: for each item unique to $old[], add to deletion buffer.
  }

  /**
   * @param \SplFileInfo $file_a
   *   File info for an on-disk file containing original
   * @param \SplFileInfo $file_b
   *   File info for an on-disk file containing modifications
   * @param string $phar_destination
   *   Path within the phar under which to dump patch data.
   */
  protected function diff(\SplFileInfo $file_a, \SplFileInfo $file_b, string $phar_destination) {
    $dmp = new DiffMatchPatch();
    $orig_stream = file_get_contents($file_a->getRealPath());
    $new_stream = file_get_contents($file_b->getRealPath());
    if ($orig_stream === $new_stream) {
      return;
    }

    echo "Making patch for $phar_destination/" . $file_b->getFilename() . "\n";
    $patches = $dmp->patch_make($orig_stream, $new_stream);

    // Add .patch file
    $filename = $file_b->getFilename();
    $this->phar->addFromString(
      "$phar_destination/$filename.patch",
      implode('', $patches)
    );

    // Add .meta file
    $meta = [
      'initial-md5' => md5($orig_stream),
      'resulting-md5' => md5($new_stream)
    ];
    $this->phar->addFromString(
      "$phar_destination/$filename.meta",
      json_encode($meta, JSON_FORCE_OBJECT)
    );
  }
}
