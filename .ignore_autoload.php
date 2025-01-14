<?php

declare(strict_types=1);

if (!\defined('DS'))
  \define('DS', \DIRECTORY_SEPARATOR);

$directory = '..' . \DS . '..' . \DS;
$ffi_list = \json_decode(\file_get_contents('.' . \DS . 'ffi_extension.json'), true);
$ext = $ffi_list['name'];
if (\file_exists($directory . '.gitignore')) {
  $ignore = \file_get_contents($directory . '.gitignore');
  if (\strpos($ignore, '.cdef/') === false) {
    $ignore .= '.cdef/' . \PHP_EOL;
    \file_put_contents($directory . '.gitignore', $ignore);
  }
} else {
  \file_put_contents($directory . '.gitignore', '.cdef' . \DS . \PHP_EOL);
}

print "- Initialized .gitignore" . PHP_EOL;

if (\file_exists($directory . '.gitattributes')) {
  $export = \file_get_contents($directory . '.gitattributes');
  if (\strpos($export, '/.cdef') === false) {
    $export .= '/.cdef       export-ignore' . \PHP_EOL;
    \file_put_contents($directory . '.gitattributes', $export);
  }
} else {
  \file_put_contents($directory . '.gitattributes', '/.cdef       export-ignore' . \PHP_EOL);
}

print "- Initialized .gitattributes" . \PHP_EOL;

$composerJson = [];
$package = '';
if (\file_exists($directory . 'composer.json')) {
  $composerJson = \json_decode(\file_get_contents($directory . 'composer.json'), true);
  $package = $composerJson['name'];
}

if (isset($composerJson['autoload'])) {
  if (isset($composerJson['autoload']['files']) && !\in_array(".cdef/$ext/preload.php", $composerJson['autoload']['files']))
    \array_push($composerJson['autoload']['files'], ".cdef/$ext/preload.php", ".cdef/$ext/src/UVFunctions.php");
  elseif (!isset($composerJson['autoload']['files']))
    $composerJson = \array_merge($composerJson, ["autoload" => ["files" => [".cdef/$ext/preload.php", ".cdef/$ext/src/UVFunctions.php"]]]);

  if (isset($composerJson['autoload']['classmap']) && !\in_array(".cdef/$ext/src/", $composerJson['autoload']['classmap']))
    \array_push($composerJson['autoload']['classmap'], ".cdef/$ext/src/");
  elseif (!isset($composerJson['autoload']['classmap']))
    $composerJson = \array_merge($composerJson, ["autoload" => ["classmap" => [".cdef/$ext/src/"]]]);
} else {
  $composerJson = \array_merge($composerJson, [
    "autoload" => [
      "files" => [
        ".cdef/$ext/preload.php",
        ".cdef/$ext/src/UVFunctions.php"
      ],
      "classmap" => [
        ".cdef/$ext/src/"
      ]
    ]
  ]);
}

if (!isset($composerJson['require']['symplely/zend-ffi']))
  \array_push($composerJson['require'], ["symplely/zend-ffi" => ">0.9.0"]);

if (isset($composerJson['require']['symplely/uv-ffi']))
  unset($composerJson['require']['symplely/uv-ffi']);

\file_put_contents(
  $directory . 'composer.json',
  \json_encode($composerJson, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES)
);

print "- Initialized `autoload` & `require` composer.json" . \PHP_EOL;

function recursiveDelete($directory, $options = [])
{
  if (!isset($options['traverseSymlinks']))
    $options['traverseSymlinks'] = false;
  $files = \array_diff(\scandir($directory), ['.', '..']);
  foreach ($files as $file) {
    $dirFile = $directory . \DS . $file;
    if (\is_dir($dirFile)) {
      if (!$options['traverseSymlinks'] && \is_link(\rtrim($file, \DS))) {
        \unlink($dirFile);
      } else {
        \recursiveDelete($dirFile, $options);
      }
    } else {
      \unlink($dirFile);
    }
  }

  return \rmdir($directory);
}

$isWindows = '\\' === \DS;
$delete = '';
if (!$isWindows) {
  \unlink($directory . '.cdef' . \DS . 'headers' . \DS . 'uv_windows.h');
  \unlink($directory . '.cdef' . \DS . 'lib' . \DS . 'Windows' . \DS . 'uv.dll');
  \rmdir($directory . '.cdef' . \DS . 'lib' . \DS . 'Windows');
  $delete .= 'Windows ';
}

if (\PHP_OS !== 'Darwin') {
  \unlink($directory . '.cdef' . \DS . 'headers' . \DS . 'uv_macos.h');
  \unlink($directory . '.cdef' . \DS . 'lib' . \DS . 'macOS' . \DS . 'libuv.1.0.0.dylib');
  \rmdir($directory . '.cdef' . \DS . 'lib' . \DS . 'macOS');
  $delete .= 'Apple macOS ';
}

if (\php_uname('m') !== 'aarch64') {
  \unlink($directory . '.cdef' . \DS . 'headers' . \DS . 'uv_pi.h');
  \unlink($directory . '.cdef' . \DS . 'lib' . \DS . 'Linux' . \DS . 'raspberry' . \DS . 'libuv.so.1.0.0');
  \rmdir($directory . '.cdef' . \DS . 'lib' . \DS . 'Linux' . \DS . 'raspberry');
  $delete .= 'Raspberry Pi ';
}

if ($isWindows)
  $version = 0;
else {
  $os = [];
  $files = \glob('/etc/*-release');
  foreach ($files as $file) {
    $lines = \array_filter(\array_map(function ($line) {
      // split value from key
      $parts = \explode('=', $line);
      // makes sure that "useless" lines are ignored (together with array_filter)
      if (\count($parts) !== 2)
        return false;

      // remove quotes, if the value is quoted
      $parts[1] = \str_replace(['"', "'"], '', $parts[1]);
      return $parts;
    }, \file($file)));

    foreach ($lines as $line)
      $os[$line[0]] = $line[1];
  }

  $id = \trim((string) $os['ID']);
  $like = \trim((string) $os['ID_LIKE']);
  $version = \trim((string) $os['VERSION_ID']);
}

if ((float)$version !== 20.04 || $isWindows) {
  \unlink($directory . '.cdef' . \DS . 'headers' . \DS . 'uv_ubuntu20.04.h');
  \unlink($directory . '.cdef' . \DS . 'lib' . \DS . 'Linux' . \DS . 'ubuntu20.04' . \DS . 'libuv.so.1.0.0');
  \rmdir($directory . '.cdef' . \DS . 'lib' . \DS . 'Linux' . \DS . 'ubuntu20.04');
  $delete .= 'Ubuntu 20.04 ';
}

if ((float)$version !== 18.04 || $isWindows) {
  \unlink($directory . '.cdef' . \DS . 'headers' . \DS . 'uv_ubuntu18.04.h');
  \unlink($directory . '.cdef' . \DS . 'lib' . \DS . 'Linux' . \DS . 'ubuntu18.04' . \DS . 'libuv.so.1.0.0');
  \rmdir($directory . '.cdef' . \DS . 'lib' . \DS . 'Linux' . \DS . 'ubuntu18.04');
  $delete .= 'Ubuntu 18.04 ';
}

if (!(float)$version >= 8 || $isWindows) {
  \unlink($directory . '.cdef' . \DS . 'headers' . \DS . 'uv_centos8+.h');
  \unlink($directory . '.cdef' . \DS . 'lib' . \DS . 'Linux' . \DS . 'centos8+' . \DS . 'libuv.so.1.0.0');
  \rmdir($directory . '.cdef' . \DS . 'lib' . \DS . 'Linux' . \DS . 'centos8+');
  $delete .= 'Centos 8+ ';
}

if (!(float)$version < 8 || $isWindows) {
  \unlink($directory . '.cdef' . \DS . 'headers' . \DS . 'uv_centos7.h');
  \unlink($directory . '.cdef' . \DS . 'lib' . \DS . 'Linux' . \DS . 'centos7' . \DS . 'libuv.so.1.0.0');
  \rmdir($directory . '.cdef' . \DS . 'lib' . \DS . 'Linux' . \DS . 'centos7');
  $delete .= 'Centos 7 ';
}

\unlink($directory . '.cdef' . \DS . 'headers' . \DS . 'original' . \DS . 'uv.h');
\recursiveDelete($directory . '.cdef' . \DS . 'headers' . \DS . 'original' . \DS . 'uv');
echo "- Removed unneeded `libuv` binary libraries and .h headers" . $delete . \PHP_EOL;

/**
 * Do not remove anything below.
 */
if (!\file_exists('..' . \DS . 'ffi_preloader.php'))
  \rename('ffi_preloader.php', '..' . \DS . 'ffi_preloader.php');
else
  \unlink('ffi_preloader.php');

\chmod($directory . '.cdef' . \DS . $ext, 0644);

// Cleanup/remove vendor directory, if previously installed as a regular composer package.
$package = \str_replace('/', \DS, $package);
if (\file_exists($directory . 'vendor' . \DS . $package . \DS . 'composer.json'))
  \recursiveDelete($directory . 'vendor' . \DS . $package);

if (!\file_exists('..' . \DS . 'ffi_preloader.php'))
  \rename('ffi_preloader.php', '..' . \DS . 'ffi_preloader.php');
else
  \unlink('ffi_preloader.php');

if (!\file_exists('..' . \DS . 'ffi_generated.json')) {
  $directories = \glob('../*', \GLOB_ONLYDIR);
  $directory = $files = [];
  foreach ($directories as $ffi_dir) {
    if (\file_exists($ffi_dir . \DS . 'ffi_extension.json')) {
      $ffi_list = \json_decode(\file_get_contents($ffi_dir . \DS . 'ffi_extension.json'), true);
      if (isset($ffi_list['preload']['directory'])) {
        \array_push($directory, $ffi_list['preload']['directory']);
      } elseif (isset($ffi_list['preload']['files'])) {
        \array_push($files, $ffi_list['preload']['files']);
      }
    }
  }

  $preload_list = [
    "preload" => [
      "files" => $files,
      "directory" => $directory
    ]
  ];
} else {
  $preload_list = \json_decode(\file_get_contents('..' . \DS . 'ffi_generated.json'), true);
  $ext_list = \json_decode(\file_get_contents('.' . \DS . 'ffi_extension.json'), true);
  if (isset($ext_list['preload']['directory'])) {
    \array_push($preload_list['preload']['directory'], $ext_list['preload']['directory']);
  } elseif (isset($ext_list['preload']['files'])) {
    \array_push($preload_list['preload']['files'], $ext_list['preload']['files']);
  }
}

\file_put_contents(
  '..' . \DS . 'ffi_generated.json',
  \json_encode($preload_list, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES)
);
\chmod($directory . '.cdef' . \DS . $ext, 0644);

\unlink(__FILE__);

print "- `.cdef/ffi_generated.json` has been updated!" . \PHP_EOL;
