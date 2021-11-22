<?php

declare(strict_types=1);

namespace OneBot\V12\Object;

trait HasExtendedData
{
    /** @var array 扩展数据 */
    private $extended_data;

    public function getExtendedData(): array
    {
        return $this->extended_data;
    }

    public function setExtendedData(array $extended_data): void
    {
        $this->extended_data = $extended_data;
    }
}
