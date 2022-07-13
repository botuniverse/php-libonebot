<?php

declare(strict_types=1);

namespace OneBot\V12\Action;

/**
 * 当未设置 Action 基础处理类时，将默认使用 ActionBase 级别的所有 Not Implemented Action
 */
class DefaultActionHandler extends ActionHandlerBase
{
}
