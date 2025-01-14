<?php

declare(strict_types=1);

use FFI\CData;
use FFI\CType;
use ZE\Zval;
use ZE\Resource;
use ZE\PhpStream;

if (!\defined('DS'))
    \define('DS', \DIRECTORY_SEPARATOR);

if (!\defined('IS_WINDOWS'))
    \define('IS_WINDOWS', ('\\' === \DS));

if (!\defined('O_RDONLY')) {
    /**
     * Open the file for read-only access.
     */
    \define('O_RDONLY', \IS_WINDOWS ? 0x0000 : UV::O_RDONLY);
}

if (!\defined('O_WRONLY')) {
    /**
     * Open the file for write-only access.
     */
    \define('O_WRONLY', \IS_WINDOWS ? 0x0001 : UV::O_WRONLY);
}

if (!\defined('O_RDWR')) {
    /**
     * Open the file for read-write access.
     */
    \define('O_RDWR', \IS_WINDOWS ? 0x0002 : UV::O_RDWR);
}

if (!\defined('O_CREAT')) {
    /**
     * The file is created if it does not already exist.
     */
    \define('O_CREAT', \IS_WINDOWS ? 0x0100 : UV::O_CREAT);
}

if (!\defined('O_EXCL')) {
    /**
     * If the O_CREAT flag is set and the file already exists,
     * fail the open.
     */
    \define('O_EXCL', \IS_WINDOWS ? 0x0400 : UV::O_EXCL);
}

if (!\defined('O_TRUNC')) {
    /**
     * If the file exists and is a regular file, and the file is
     * opened successfully for write access, its length shall be truncated to zero.
     */
    \define('O_TRUNC', \IS_WINDOWS ? 0x0200 : UV::O_TRUNC);
}

if (!\defined('O_APPEND')) {
    /**
     * The file is opened in append mode. Before each write,
     * the file offset is positioned at the end of the file.
     */
    \define('O_APPEND', \IS_WINDOWS ? 0x0008 : UV::O_APPEND);
}

if (!\defined('O_NOCTTY') && !\IS_WINDOWS) {
    /**
     * If the path identifies a terminal device, opening the path will not cause that
     * terminal to become the controlling terminal for the process (if the process does
     * not already have one).
     *
     * - Note O_NOCTTY is not supported on Windows.
     */
    \define('O_NOCTTY', UV::O_NOCTTY);
}

if (!\class_exists('ext_uv')) {
    final class ext_uv extends \StandardModule
    {
        protected string $ffi_tag = 'uv';
        protected string $module_name = 'uv';
        protected string $module_version = '0.3.0';
        protected ?string $global_type = 'uv_globals';
        protected bool $m_startup = true;
        protected bool $m_shutdown = true;
        protected bool $r_shutdown = true;

        protected string $uv_version;

        /** @var \UVLoop[]|null */
        protected $uv_default;

        protected ?CData $default_mutex = null;

        public function get_mutex(): ?CData
        {
            return (\PHP_ZTS) ? $this->default_mutex : null;
        }

        public function set_default(?\UVLoop $loop): void
        {
            if (\PHP_ZTS)
                $this->uv_default[\ze_ffi()->tsrm_thread_id()] = $loop;
            else
                $this->uv_default = $loop;
        }

        public function get_default(): ?\UVLoop
        {
            if (\PHP_ZTS)
                return $this->uv_default[\ze_ffi()->tsrm_thread_id()] ?? null;

            return $this->uv_default;
        }

        public function module_startup(int $type, int $module_number): int
        {
            if (\PHP_ZTS)
                $this->default_mutex = \ze_ffi()->tsrm_mutex_alloc();

            if (\IS_WINDOWS)
                $this->destruct_set();

            $this->uv_version = \uv_ffi()->uv_version_string();
            \ext_uv::set_module($this);
            \Core::setup_stdio();
            return \ZE::SUCCESS;
        }

        public function module_shutdown(int $type, int $module_number): int
        {
            \ext_uv::set_module(null);
            \Core::clear_stdio();
            \Core::clear('uv');

            if (\PHP_ZTS) {
                \ze_ffi()->tsrm_mutex_free($this->default_mutex);
                $this->default_mutex = null;
            }

            return \ZE::SUCCESS;
        }

        public function request_shutdown(...$args): int
        {
            if (\is_ze_ffi()) {
                $uv_loop = $this->get_default();
                if ($uv_loop instanceof \UVLoop && \is_cdata($uv_loop()))
                    $uv_loop->__destruct();
            }

            return \ZE::SUCCESS;
        }

        public function module_info(CData $entry): void
        {
            \ze_ffi()->php_printf('PHP lib' . $entry->name . "-ffi Extension\n");
            \ze_ffi()->php_info_print_table_start();
            \ze_ffi()->php_info_print_table_header(2, "libuv Support", "enabled");
            \ze_ffi()->php_info_print_table_row(2, "Version", $this->module_version);
            \ze_ffi()->php_info_print_table_row(2, "libuv Version", $this->uv_version);
            \ze_ffi()->php_info_print_table_end();
        }
    }
}

if (!\function_exists('uv_init')) {
    /**
     * Represents **ext-uv** `UV_G()` _macro_.
     *
     * @param string|null $element
     * @param mixed $initialize set element value
     * @return CData|null
     */
    function uv_g(?string $element = null, $initialize = 'empty'): ?CData
    {
        return \ext_uv::get_module()->get_globals($element, $initialize);
    }

    /**
     * Returns **cast** a `uv_req_t` _base request_ pointer.
     *
     * @param object $ptr
     * @return CData uv_req_t
     */
    function uv_request(object $ptr): ?CData
    {
        return \Core::cast('uv', 'uv_req_t*', \uv_object($ptr));
    }

    /**
     * Returns **cast** a `uv` pointer as `typedef`.
     *
     * @param string $typedef
     * @param object|CData $ptr
     * @return CData
     */
    function uv_cast(string $typedef, $ptr): CData
    {
        return \Core::cast('uv', $typedef, \uv_object($ptr));
    }

    /**
     * Returns **cast** a `uv_stream_t` _stream_ pointer.
     *
     * @param object $ptr
     * @return CData uv_stream_t
     */
    function uv_stream(object $ptr): CData
    {
        $stream = \uv_object($ptr);
        return \is_typeof($stream, 'struct uv_stream_s*')  ? $stream : \Core::cast('uv', 'uv_stream_t*', $stream);
    }

    /**
     * Returns **cast** a `uv_handle_t` _base handle_ pointer.
     *
     * @param object $ptr
     * @return CData uv_handle_t
     */
    function uv_handle(object $ptr): CData
    {
        if ($ptr instanceof \UV)
            return $ptr(true);

        $handle = \uv_object($ptr);
        return \is_typeof($handle, 'struct uv_handle_s*')  ? $handle : \Core::cast('uv', 'uv_handle_t*', $handle);
    }

    /**
     * Returns **cast** a `sockaddr` _address and port base structure_ pointer.
     *
     * @param UVSockAddr|sockaddr_in|sockaddr_in6 $ptr
     * @return CData sockaddr
     */
    function uv_sockaddr(object $ptr): CData
    {
        return \Core::cast('uv', 'struct sockaddr*', \uv_object($ptr));
    }

    /**
     * Checks `handle` and returns the `CData` object within.
     *
     * @param UV|object|CData $handle
     * @return CData|mixed
     */
    function uv_object($handle)
    {
        $handler = $handle;
        if (
            $handle instanceof \UV
            || $handle instanceof \UVLoop
            || $handle instanceof \UVStream
            || $handle instanceof \UVTypes
            || $handle instanceof \CStruct
        )
            $handler = $handle();

        return $handler;
    }

    /**
     * Manually removes an previously created `C` data memory pointer.
     *
     * @param \UV|\CStruct|CData $ptr
     * @return void
     */
    function uv_ffi_free(object $ptr): void
    {
        if ($ptr instanceof \UV || $ptr instanceof \UVTypes || $ptr instanceof \CStruct)
            $ptr->free();
        elseif (\is_cdata($ptr))
            \FFI::free($ptr);
    }

    /**
     * @param CData $fd_ptr
     * @param integer $fd
     * @param \UVFs $req
     * @return resource
     */
    function create_uv_fs_resource(CData $fd_ptr, int $fd, \UVFs $req)
    {
        $fd_res = \zend_register_resource(
            $fd_ptr,
            \zend_register_list_destructors_ex(
                function (CData $rsrc) {
                    \uv_ffi()->uv_fs_req_cleanup(\uv_cast('uv_fs_t*', $rsrc->ptr));
                },
                null,
                'stream',
                20220101
            )
        );

        $fd_zval = \zval_resource($fd_res);
        $resource = \zval_native($fd_zval);
        $file = \fd_type();
        $file->update($fd_ptr, true);
        $file->add_object($req);
        $file->add_pair($fd_zval, $fd, (int)$resource);

        return $resource;
    }

    /**
     * Represents _ext-uv_ `php_uv_stat_to_zval` and `php_uv_make_stat` functions.
     *
     * @param CData|uv_stat_t $stat
     * @return array
     */
    function uv_stat_to_zval(CData $stat): array
    {
        $arrays = \zval_array(\ze_ffi()->_zend_new_array(0));
        $array = $arrays();
        \ze_ffi()->add_assoc_long_ex($array, "dev", \strlen("dev"), $stat->st_dev);
        \ze_ffi()->add_assoc_long_ex($array, "ino", \strlen("ino"), $stat->st_ino);
        \ze_ffi()->add_assoc_long_ex($array, "mode", \strlen("mode"), $stat->st_mode);
        \ze_ffi()->add_assoc_long_ex($array, "nlink", \strlen("nlink"), $stat->st_nlink);
        \ze_ffi()->add_assoc_long_ex($array, "uid", \strlen("uid"), $stat->st_uid);
        \ze_ffi()->add_assoc_long_ex($array, "gid", \strlen("gid"), $stat->st_gid);
        \ze_ffi()->add_assoc_long_ex($array, "rdev", \strlen("rdev"), $stat->st_rdev);
        \ze_ffi()->add_assoc_long_ex($array, "size", \strlen("size"), $stat->st_size);

        if (\IS_LINUX) {
            \ze_ffi()->add_assoc_long_ex($array, "blksize", \strlen("blksize"), $stat->st_blksize);
            \ze_ffi()->add_assoc_long_ex($array, "blocks", \strlen("blocks"), $stat->st_blocks);
        }

        \ze_ffi()->add_assoc_long_ex($array, "atime", \strlen("atime"), $stat->st_atim->tv_sec);
        \ze_ffi()->add_assoc_long_ex($array, "mtime", \strlen("mtime"), $stat->st_mtim->tv_sec);
        \ze_ffi()->add_assoc_long_ex($array, "ctime", \strlen("ctime"), $stat->st_ctim->tv_sec);

        return \zval_native($arrays);
    }

    /**
     * Represents _ext-uv_ `php_uv_address_to_zval` function.
     *
     * @param UVSockAddr|sockaddr $addr
     * @return array
     */
    function uv_address_to_array(\UVSockAddr $addr): array
    {
        $ip = \ffi_characters(\INET6_ADDRSTRLEN);
        switch ($addr->family()) {
            case \AF_INET6:
                $a6 = \uv_cast('struct sockaddr_in6 *', $addr);
                // $ip = \uv_inet_ntop(\AF_INET6, $a6);
                \uv_ffi()->uv_ip6_name($a6, $ip, \INET6_ADDRSTRLEN);
                $port = \ntohs($a6->sin6_port);
                $family = 'IPv6';
                break;
            case \AF_INET:
                $a4 = \uv_cast('struct sockaddr_in *', $addr);
                // $ip = \uv_inet_ntop(\AF_INET, $a4);
                \uv_ffi()->uv_ip4_name($a4, $ip, \INET6_ADDRSTRLEN);
                $port = \ntohs($a4->sin_port);
                $family = 'IPv4';
                break;
            default:
                break;
        }

        \zval_del_ref($addr);
        return ['address' => \ffi_string($ip), 'port' => $port, 'family' => $family];
    }

    function uv_ffi(): \FFI
    {
        return \Core::get('uv');
    }

    /**
     * Checks whether the given object is `UVStream` or `uv_stream_t`.
     *
     * @param mixed $ptr
     * @return boolean
     */
    function is_uv_stream(object $ptr): bool
    {
        return \in_array(\ffi_str_typeof(\uv_object($ptr)), [
            'struct uv_tcp_s*', 'struct uv_pipe_s*',
            'struct uv_tty_s*', 'struct uv_stream_s*'
        ], true);
    }

    /**
     * Check for _active_ `UV` **ffi** instance
     *
     * @return boolean
     */
    function is_uv_ffi(): bool
    {
        return Core::get('uv') instanceof \FFI;
    }

    /**
     * **Setup** - *creates* a new **UV FFI** object or *retrieve* current `scoped`_(preloaded)_ object.
     *
     * @return void
     * @throws \RuntimeException
     */
    function uv_init(): void
    {
        if (!\is_uv_ffi()) {
            // Try if preloaded
            try {
                \Core::set('uv', \FFI::scope('__uv__'));
            } catch (Exception $e) {
                \uv_ffi_loader();
            }

            if (!\is_uv_ffi()) {
                throw new \RuntimeException("FFI parse failed!");
            }
        }
    }

    function uv_ffi_loader()
    {
        $directory = __DIR__ . \DS;
        if (\IS_WINDOWS) {
            $code = $directory . 'headers\\uv_windows.h';
        } elseif (\PHP_OS === 'Darwin') {
            $code = $directory . 'headers/uv_macos.h';
        } elseif (\php_uname('m') === 'aarch64') {
            $code = $directory . 'headers/uv_pi.h';
        } else {
            /**
             * Get the `Linux` distribution info and version.
             * [DISTRIB_ID] => Ubuntu
             * [DISTRIB_RELEASE] => 13.04
             * [DISTRIB_CODENAME] => raring
             * [DISTRIB_DESCRIPTION] => Ubuntu 13.04
             * [NAME] => Ubuntu
             * [VERSION] => 13.04, Raring Ringtail
             * [ID] => ubuntu
             * [ID_LIKE] => debian
             * [PRETTY_NAME] => Ubuntu 13.04
             * [VERSION_ID] => 13.04
             * [HOME_URL] => http://www.ubuntu.com/
             * [SUPPORT_URL] => http://help.ubuntu.com/
             * [BUG_REPORT_URL] => http://bugs.launchpad.net/ubuntu/
             */
            $os = [];
            $files = \glob('/etc/*-release');
            foreach ($files as $file) {
                $lines = \array_filter(\array_map(function ($line) {
                    $parts = \explode('=', $line);
                    if (\count($parts) !== 2)
                        return false;

                    $parts[1] = \str_replace(['"', "'"], '', $parts[1]);
                    return $parts;
                }, \file($file)));

                foreach ($lines as $line)
                    $os[$line[0]] = $line[1];
            }

            $id = \trim((string) $os['ID_LIKE']);
            $version = \trim((string) $os['VERSION_ID']);
            if ($id === 'debian') {
                $code = $directory . 'headers/uv_ubuntu' . ((float)$version < 20.04 ? '18.04' : '20.04') . '.h';
            } elseif ($id === 'redhat') {
                $code = $directory . 'headers/uv_centos' . ((float)$version < 8 ? '7' : '8+') . '.h';
            }
        }

        $scope = \FFI::load($code);
        if (\file_exists('.' . \DS . 'ffi_extension.json')) {
            $ext_list = \json_decode(\file_get_contents('.' . \DS . 'ffi_extension.json'), true);
            $isDir = false;
            $iterator = [];
            $is_opcache_cli = \ini_get('opcache.enable_cli') === '1';
            if (isset($ext_list['preload']['directory'])) {
                $isDir = true;
                $directory = \array_shift($ext_list['preload']['directory']);
                $dir = new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::KEY_AS_PATHNAME);
                $iterator = new \RecursiveIteratorIterator($dir, \RecursiveIteratorIterator::SELF_FIRST);
            } elseif (isset($ext_list['preload']['files'])) {
                $iterator = $ext_list['preload']['files'];
            }

            foreach ($iterator as $fileInfo) {
                if ($isDir && !$fileInfo->isFile()) {
                    continue;
                }

                $file = $isDir ? $fileInfo->getPathname() : $fileInfo;
                if ($is_opcache_cli) {
                    if (!\opcache_is_script_cached($file))
                        \opcache_compile_file($file);
                } else {
                    include_once $file;
                }
            }
        }

        \Core::set('uv', $scope);
        $ext_uv = new \ext_uv(true);
        if (!$ext_uv->is_registered()) {
            $ext_uv->register();
            $ext_uv->startup();
        }
    }

    \uv_ffi_loader();
}
