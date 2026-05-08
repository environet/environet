# Migration Guide for 2.0

## Distribution Node Upgrade Guide

### PostgreSQL 12 to 18 Upgrade Guide

##### Prerequisites
- Access to the terminal where Docker Compose is running.
- The `.env` file containing your `POSTGRES_USER`, `POSTGRES_PASSWORD`, and `POSTGRES_DB` variables, and these variables are the correct ones for your current setup.

---

#### Step 1: Export Data (from PG 12)
While the old container is still running, create a full backup. The `-c` flag ensures `DROP` commands are included for a clean restore
```bash
docker compose --env-file=.env -p environet -f docker/docker-compose.distribution.yml exec -t dist_database pg_dumpall -c -U environet > dump.sql
```

#### Step 2: Stop Services and Prepare Storage
Stop the project and move the old data directory. PostgreSQL 18+ uses a different directory structure and cannot read PG 12 data files directly
```bash
./environet dist down
mv data/postgres data/postgres_old
```

#### Step 3: Update and Start PG 18
Pull the git repository changes, and start the database container. It will contain the updated Dockerfile and docker compose changes to use PostgreSQL 18. The new container will initialize with a fresh data directory, and you will import the old data in the next step.

```bash
#Pull the latest changes of the docker and the src repository
git checkout master
git pull
cd src
git checkout master
git pull

# Build the new image
docker compose --env-file=.env -p environet -f docker/docker-compose.distribution.yml build dist_database

# Start the services
./environet dist up
```

#### Step 4: Import Data
Pipe the backup into the new container. Note the use of `-T` to disable TTY.
```bash
cat dump.sql | docker compose --env-file=.env -p environet -f docker/docker-compose.distribution.yml exec -T dist_database psql -U environet
```

#### Step 5: Fix Authentication (SCRAM-SHA-256)
PostgreSQL 18 requires SCRAM encryption. Since the dump overwritten the user with an old MD5 hash, you must reset the password to generate a valid SCRAM secret.
```bash
# Connect to the database
docker compose --env-file=.env -p environet -f docker/docker-compose.distribution.yml exec dist_database psql -U environet -d postgres

# Inside the psql prompt, run:
ALTER ROLE environet WITH PASSWORD 'your_password_here';
\q
```

#### Step 6: Post-Upgrade Optimization
Rebuild the optimizer statistics to ensure query performance:
```bash
docker compose --env-file=.env -p environet -f docker/docker-compose.distribution.yml exec dist_database vacuumdb -U environet --all --analyze-only
```
---


#### Step 7: Rebuild dist and data php containers
After the database upgrade, you should also rebuild the `dist` and `data` containers.
```bash
docker compose --env-file=.env -p environet -f docker/docker-compose.distribution.yml build dist_php
docker compose --env-file=.env -p environet -f docker/docker-compose.data.yml build data_php

./environet dist up
```

****


## Data node upgrade guide

##### Prerequisites
- Access to the terminal where Docker Compose is running.

---

#### Step 1: Stop Services
Stop the project services
```bash
./environet data down
```

#### Step 2: Update the repository and build a new image
Pull the git repository changes, and start the database container. It will contain the updated Dockerfile and docker compose changes.

```bash
#Pull the latest changes from the repository
git pull

# Build the new image
docker compose --env-file=.env -p environet -f docker/docker-compose.data.yml build data_php
```

#### Step 3: Start services

```bash
./environet data up
```