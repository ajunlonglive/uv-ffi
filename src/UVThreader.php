<?php

declare(strict_types=1);

use FFI\CData;
use FFI\CType;

if (!\class_exists('UVThreader')) {
    abstract class UVThreader extends \CStruct
    {
        protected string $type;
        protected ?CData $struct_base;

        const IS_UV_RWLOCK      = 1;
        const IS_UV_RWLOCK_RD   = 2;
        const IS_UV_RWLOCK_WR   = 3;
        const IS_UV_MUTEX       = 4;
        const IS_UV_SEMAPHORE   = 5;
        const UV_LOCK_TYPE      = [
            'rwlock'    => self::IS_UV_RWLOCK,
            'mutex'     => self::IS_UV_MUTEX,
            'semaphore' => self::IS_UV_SEMAPHORE,
        ];

        public function __destruct()
        {
            if ($this->struct_base->type == self::IS_UV_RWLOCK) {
                if ($this->struct_base->locked == 0x01) {
                    \ze_ffi()->zend_error(\E_NOTICE, "uv_rwlock: still locked resource detected; forcing wrunlock");
                    \uv_ffi()->uv_rwlock_wrunlock($this->struct_ptr);
                } else if ($this->struct_base->locked) {
                    \ze_ffi()->zend_error(\E_NOTICE, "uv_rwlock: still locked resource detected; forcing rdunlock");
                    while (--$this->struct_base->locked > 0) {
                        \uv_ffi()->uv_rwlock_rdunlock($this->struct_ptr);
                    }
                }

                \uv_ffi()->uv_rwlock_destroy($this->struct_ptr);
            } else if ($this->struct_base->type == self::IS_UV_MUTEX) {
                if ($this->struct_base->locked == 0x01) {
                    \ze_ffi()->zend_error(\E_NOTICE, "uv_mutex: still locked resource detected; forcing unlock");
                    \uv_ffi()->uv_mutex_unlock($this->struct_ptr);
                }

                \uv_ffi()->uv_mutex_destroy($this->struct_ptr);
            } else if ($this->struct_base->type == self::IS_UV_SEMAPHORE) {
                if ($this->struct_base->locked == 0x01) {
                    \ze_ffi()->zend_error(\E_NOTICE, "uv_sem: still locked resource detected; forcing unlock");
                    \uv_ffi()->uv_sem_post($this->struct_ptr);
                }

                \uv_ffi()->uv_sem_destroy($this->struct_ptr);
            }

            $this->free();
        }

        protected function __construct(
            $typedef,
            string $type = '',
            array $initializer = null,
            bool $isSelf = false
        ) {
            $this->tag = 'uv';
            $this->type = $type;
            if (!$isSelf || \is_string($typedef)) {
                $this->struct = \Core::get($this->tag)->new('struct ' . $typedef);
                $this->struct_base = \FFI::addr($this->struct);
                $this->struct_ptr = \FFI::addr($this->struct->lock->{$type});
                $this->struct_base->type = self::UV_LOCK_TYPE["$type"] ?? null;
            } else {
                $this->struct = \Core::get($this->tag)->new($typedef);
            }
        }

        public function __invoke(bool $byBase = false): CData
        {
            if ($byBase) {
                if (\is_null($this->struct_base)) {
                    $this->struct_base = \FFI::addr($this->struct);
                    $this->struct_base->type = self::UV_LOCK_TYPE[$this->type] ?? null;
                }

                return $this->struct_base;
            }

            if (\is_null($this->struct_ptr)) {
                $this->struct_ptr = \FFI::addr($this->struct->lock->{$this->type});
            }

            return $this->struct_ptr;
        }

        public function free(): void
        {
            if (\is_cdata($this->struct_ptr) && !$this->isNull())
                \FFI::free($this->struct_ptr);

            if (\is_cdata($this->struct_base) && !\is_null_ptr($this->struct_base))
                \FFI::free($this->struct_base);

            $this->struct_ptr = null;
            $this->struct_base = null;
            $this->struct = null;
            $this->type = '';
            $this->tag = '';
        }
    }
}
