<?php

declare(strict_types=1);

namespace Vizuh\ClickTrail\Models;

use System\Models\SettingModel;

/**
 * Plugin settings managed through the October backend Settings area
 * (standard SettingModel integration - no custom controller needed).
 */
class Settings extends SettingModel
{
    public $settingsCode = 'vizuh_clicktrail_settings';

    public $settingsFields = 'fields.yaml';
}
