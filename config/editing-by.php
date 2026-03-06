<?php

return [
    'table' => 'model_editings',
    'default_ttl_seconds' => 20,
    'user_model' => env('EDITING_BY_USER_MODEL', config('auth.providers.users.model')),
    'user_table' => env('EDITING_BY_USER_TABLE'),
    'user_key_name' => env('EDITING_BY_USER_KEY_NAME'),
    'user_key_column_type' => env('EDITING_BY_USER_KEY_COLUMN_TYPE', 'auto'),
    'prune_expired_schedule' => true,
];
