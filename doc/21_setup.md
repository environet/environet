# Setup

1. Install the environet project. Refer to [Setup](11_setup.md).

2. Create the .env file in the root folder, with copying the .env.example.minimal file. This file contains the mandatory properties for the successful install.
   
   `cp .env.example.minimal .env`
   
   Before next command it is possible the customize the database name, database username, and database password in the new .env file.

3. Create a distribution node configuration
   `./environet dist install`
   
4. Initialize database, and create admin user
   `./environet dist database init`

After going through these steps, the distribution node should be up and running. You can access the admin panel at YOUR_IP/admin.

# Updates

After updating your deployment, you need to run `./environet dist database migrate`, to run any database migrations that might be included in the update.  