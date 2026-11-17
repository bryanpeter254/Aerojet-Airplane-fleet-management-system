AEROJET - PHP + MySQL backend
1. Copy this folder to htdocs (XAMPP) or your web root.
2. Import database.sql in phpMyAdmin.
3. Edit DB_* in config.php (limits are also there: LIMIT_FLIGHT, LIMIT_DUTY, LIMIT_DAYS, WARN_RATIO).
4. Open /setup.php once (creates admin@aerojet.test / ChangeMe123!), then DELETE setup.php.
5. Open /index.html and sign in.
API: api.php?action=list|create|update|delete&table=voyages|crew|crew_log|crew_roster|dispatchers|postholders|aircraft_docs|flight_types
