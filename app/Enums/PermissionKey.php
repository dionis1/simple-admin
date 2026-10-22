<?php

namespace App\Enums;

enum PermissionKey: string
{
    case ViewDashboard = 'dashboard.view';
    case ViewOrganization = 'organizations.view';
    case UpdateOrganization = 'organizations.update';
    case ViewMemberships = 'memberships.view';
    case CreateMemberships = 'memberships.create';
    case UpdateMemberships = 'memberships.update';
    case DeleteMemberships = 'memberships.delete';
    case ViewRoles = 'roles.view';
    case AssignRoles = 'roles.assign';
    case ViewPermissions = 'permissions.view';
}
