<?php

namespace App\Enums;

enum CompanyStatus: string
{
    case Pending = 'pending';
    case Provisioning = 'provisioning';
    case Active = 'active';
    case ProvisioningFailed = 'provisioning_failed';
    case Suspended = 'suspended';
    case Deactivated = 'deactivated';
}
