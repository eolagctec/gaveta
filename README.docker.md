# README additions: docker + seeds + sessions

## Docker (development)

1. Copy example env and edit if desired:
   cp .env.example .env

2. Build and start services:
   docker-compose up -d --build

3. Run migrations once (create DB schema):
   mysql -u root -p < migrations.sql
   # or use the Makefile if you mounted files correctly

4. Seed sample data:
   mysql -u root -p ${MYSQL_DATABASE} < seeds.sql

5. Open the UI:
   http://localhost:8000

Notes:
- The Docker image installs the PHP Redis extension and configures Apache.
- Sessions are stored via PHP session handling; if you want to use Redis as session store, ensure REDIS_HOST/PORT are set and uncomment the Redis session lines in `api.php`.

