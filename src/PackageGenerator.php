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

    $this->processTreeDirectory($from_version, $to_version, '');
  }

  /**
   * @param string $from_version
   * @param string $to_version
   * @param string $path
   *   A path that is a directory under at least one of the versions' trees.
   */
  protected function processTreeDirectory(string $from_version, string $to_version, string $path) {
    $on_disk_old = "release_trees/$from_version/$path";
    $on_disk_new = "release_trees/$to_version/$path";

    // TODO: Handle case where old or new path is not a directory
    $old_reader = new \FilesystemIterator($on_disk_old, \FilesystemIterator::KEY_AS_PATHNAME);
    $old = [];
    foreach ($old_reader as $p => $file_info) {
      $old[substr($p, strlen($on_disk_old))] = $file_info;
    }
    unset($old_reader);

    $new_reader = new \FilesystemIterator($on_disk_new, \FilesystemIterator::KEY_AS_PATHNAME);
    $new = [];
    foreach ($new_reader as $p => $file_info) {
      $new[substr($p, strlen($on_disk_new))] = $file_info;
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
        $this->processTreeDirectory($from_version, $to_version, $path . DIRECTORY_SEPARATOR . $new_file_info->getFilename());
      } else {
        fwrite(STDERR, sprintf("WARNING: ignoring %s because it is of unsupported type \"%s\".\n",
          "$path/" . $new_file_info->getFilename(),
          $new_file_info->getType()
          )
        );
      }
    }
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
