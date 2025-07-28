<?php

use putyourlightson\blitz\models\SettingsModel;

dataset('refresh mode clear', [
    'clear only' => SettingsModel::REFRESH_MODE_CLEAR,
    'clear and generate' => SettingsModel::REFRESH_MODE_CLEAR_AND_GENERATE,
]);

dataset('refresh mode expire', [
    'expire only' => SettingsModel::REFRESH_MODE_EXPIRE,
    'expire and generate' => SettingsModel::REFRESH_MODE_EXPIRE_AND_GENERATE,
]);

dataset('refresh mode manual', [
    'clear only' => SettingsModel::REFRESH_MODE_CLEAR,
    'expire only' => SettingsModel::REFRESH_MODE_EXPIRE,
]);

dataset('refresh mode generate', [
    'clear and generate' => SettingsModel::REFRESH_MODE_CLEAR_AND_GENERATE,
    'expire and generate' => SettingsModel::REFRESH_MODE_EXPIRE_AND_GENERATE,
]);
