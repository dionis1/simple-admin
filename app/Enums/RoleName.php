<?php

namespace App\Enums;

enum RoleName: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Manager = 'manager';
    case Support = 'support';
    case Viewer = 'viewer';

    public function rank(): int
    {
        return match ($this) {
            self::Owner => 100,
            self::Admin => 80,
            self::Manager => 60,
            self::Support => 40,
            self::Viewer => 20,
        };
    }

    /** @return list<PermissionKey> */
    public function permissions(): array
    {
        return match ($this) {
            self::Owner, self::Admin => PermissionKey::cases(),
            self::Manager => [PermissionKey::ViewTeams, PermissionKey::ManageTeams, PermissionKey::ViewDepartments, PermissionKey::ManageDepartments, PermissionKey::ViewDashboard, PermissionKey::ViewOrganization, PermissionKey::ViewMemberships, PermissionKey::ViewRoles, PermissionKey::ViewPermissions],
            self::Support => [PermissionKey::ViewTeams, PermissionKey::ViewDepartments, PermissionKey::ViewDashboard, PermissionKey::ViewOrganization, PermissionKey::ViewMemberships],
            self::Viewer => [PermissionKey::ViewTeams, PermissionKey::ViewDepartments, PermissionKey::ViewDashboard, PermissionKey::ViewOrganization],
        };
    }
}
