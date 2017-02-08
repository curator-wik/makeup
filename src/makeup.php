<?php
namespace curator\makeup;
require __DIR__ . '/../vendor/autoload.php';

use Ulrichsg\Getopt\Getopt;
use Ulrichsg\Getopt\Option;

const VERSION = '0.1.0';

$opts = new Getopt([
  (new Option('d', 'directory', Getopt::REQUIRED_ARGUMENT))
    ->setDescription('Directory to read package source and release trees from, and write update package to. Defaults to the current directory.'),
  (new Option('v', 'version', Getopt::NO_ARGUMENT))
    ->setDescription('Print version information and exit.'),
  (new Option('h', 'help', Getopt::NO_ARGUMENT))
    ->setDescription('Print this help text and exit.')
]);

$opts->setBanner("Makes a Curator update package from a directory of source trees and metedata.\n");

try {
  $opts->parse();
} catch (\UnexpectedValueException $e) {
  fwrite(STDERR, sprintf("Invalid options: %s\n", $e->getMessage()));
  echo "\n";
  echo $opts->getHelpText();
  exit(1);
}

if ($opts['h']) {
  echo $opts->getHelpText();
  exit(0);
}

if ($opts['v']) {
  printf("version %s\n", VERSION);
  exit(0);
}

if ($opts['d']) {
  if (! chdir($opts['d'])) {
    fwrite(STDERR, sprintf("Could not change to directory \"%s\".\n", $opts['d']));
    exit(1);
  }
}

$util = new Util();

$util->validateDirectory();

$releases = $util->getReleaseList();
