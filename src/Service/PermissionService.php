<?php

namespace Mie\FlarumFiles\Service;

use Flarum\User\User;

final class PermissionService
{
    public static function key(string $permission, string $action): string
    {
        if (!in_array($action, ['view', 'download', 'upload'], true)) throw new \InvalidArgumentException('Invalid file permission action.');
        return 'mie-files.category.'.$permission.'.'.$action;
    }
    public static function can(User $actor, string $permission, string $action): bool
    {
        return $actor->isAdmin() || $actor->can(self::key($permission, $action));
    }
}
