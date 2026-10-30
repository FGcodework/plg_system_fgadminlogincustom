<?php

/** Minimal stand-ins for the Joomla classes the helpers touch. */

namespace Joomla\Registry {
    class Registry
    {
        public function __construct(private array $d = [])
        {
        }

        public function get(string $k, $default = null)
        {
            return $this->d[$k] ?? $default;
        }

        public function set(string $k, $v): void
        {
            $this->d[$k] = $v;
        }

        public function toArray(): array
        {
            return $this->d;
        }
    }
}

namespace Joomla\CMS\Uri {
    class Uri
    {
        public static function root(bool $pathOnly = false): string
        {
            return '/site';
        }
    }
}
