<?php

declare(strict_types=1);

use FFI\CData;

if (!\class_exists('UVTypes')) {
    abstract class UVTypes
    {
        protected ?CData $uv_type = null;
        protected ?CData $uv_type_ptr = null;

        public function __destruct()
        {
            $this->free();
        }

        protected function __construct(string $typedef)
        {
            $this->uv_type = \uv_ffi()->new($typedef);
            $this->uv_type_ptr = \ffi_ptr($this->uv_type);
        }

        public function __invoke()
        {
            return $this->uv_type_ptr;
        }

        /**
         * Manually removes `C data` structure pointer memory, and any held `instance`.
         *
         * @return void
         */
        public function free(): void
        {
            if (\is_cdata($this->uv_type_ptr) && !\is_null_ptr($this->uv_type_ptr)) {
                \FFI::free($this->uv_type_ptr);
            }

            $this->uv_type_ptr = null;
            $this->uv_type = null;
        }

        /** @return static */
        public static function init(...$arguments)
        {
            return new static(\reset($arguments));
        }
    }
}
