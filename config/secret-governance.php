<?php

return [
    'schema_version' => 1,
    'definitions' => [
        'secret://application/app-key' => ['component' => 'application', 'classification' => 'critical', 'source' => 'environment', 'config_path' => 'app.key', 'required' => true, 'rotation_days' => 365],
        'secret://database/primary' => ['component' => 'database', 'classification' => 'critical', 'source' => 'environment', 'config_path' => 'database.connections.mysql.password', 'required' => true, 'rotation_days' => 90],
        'secret://cache/redis' => ['component' => 'cache', 'classification' => 'restricted', 'source' => 'environment', 'config_path' => 'database.redis.default.password', 'required' => false, 'rotation_days' => 90],
        'secret://notification/mail' => ['component' => 'notification', 'classification' => 'restricted', 'source' => 'environment', 'config_path' => 'mail.mailers.smtp.password', 'required' => false, 'rotation_days' => 90],
        'secret://realtime/reverb' => ['component' => 'realtime', 'classification' => 'restricted', 'source' => 'environment', 'config_path' => 'reverb.apps.apps.0.secret', 'required' => false, 'rotation_days' => 90],
        'secret://integration/encrypted-connection' => ['component' => 'integration', 'classification' => 'critical', 'source' => 'encrypted_store', 'config_path' => null, 'required' => false, 'rotation_days' => 90],
        'secret://storage/object-store' => ['component' => 'storage', 'classification' => 'critical', 'source' => 'environment', 'config_path' => 'filesystems.disks.s3.secret', 'required' => false, 'rotation_days' => 90],
        'secret://media/cloudflare-images' => ['component' => 'media', 'classification' => 'critical', 'source' => 'environment', 'config_path' => 'services.cloudflare_images.api_token', 'required' => false, 'rotation_days' => 90],
        'secret://identity/google-client' => ['component' => 'identity', 'classification' => 'critical', 'source' => 'environment', 'config_path' => 'services.google.client_secret', 'required' => false, 'rotation_days' => 90],
        'secret://identity/facebook-client' => ['component' => 'identity', 'classification' => 'critical', 'source' => 'environment', 'config_path' => 'services.facebook.client_secret', 'required' => false, 'rotation_days' => 90],
        'secret://identity/passport-private-key' => ['component' => 'identity', 'classification' => 'critical', 'source' => 'environment', 'config_path' => 'passport.private_key', 'required' => false, 'rotation_days' => 365],
    ],
];
