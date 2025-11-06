<?php
return array(
  'APP_NAME'      => 'SupportOps',
  'APP_URL'       => 'https://alqatta-sizing.com/supportops/public',
  'DB_PATH'       => __DIR__ . '/../database/app.db',
  'UPLOAD_DIR'    => __DIR__ . '/../public/uploads',
  'ALLOWED_UPLOADS' => array('image/jpeg','image/png','application/pdf','text/plain','text/csv'),
  'MAX_UPLOAD_MB' => 20,
  'SESSION_NAME'  => 'supportops_sid',
  'SLA_HOURS'     => 48,
  'BACKUP_DIR'    => __DIR__ . '/../backup/daily',
);