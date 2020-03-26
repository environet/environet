# Data node functionality

A data node is a special configuration of Environet, which runs at a data source with the purpose of gathering metering point measurements from any third party format, such as a plain text file, spreadsheet or web resource, transform these data to a format compatible with the Environet distribution node API, and upload the results to a distribution node on behalf of an API user.
The gathering and uploading of data is accomplished by way of uploader plugins, which are defined by configuration files which can then be run by calling a script, typically by a cron job.

## Required software
Docker engine - https://docs.docker.com/install/
Docker compose - https://docs.docker.com/compose/

## Setup

1. Clone the two git repositories needed to run the app:

- The Environet docker environment: https://github.com/environet/environet-docker
- The Environet source code https://github.com/environet/environet

2. Configure docker

In the Environet docker project folder, create a new .env file by copying the .env.example.

- Change the SRC_ROOT variable to the path of the Environet source code folder (this would be "../environet" if the two repositories are in the same folder, or it can be set to an absolute path)
- If your data node will be reading data from the filesystem, configure the LOCAL_DATA_DIR variable with the path to the directory where the data file(s) will be found.

3. Start the data node container

- `docker-compose up -d`

4. Create your uploader plugin configuration

For an uploader configuration, any metering points and observable properties for which the plugin will upload measurements, have to be configured beforehand on the Environet distribution node that the plugin will be uploading to.
A user name and ssl private key will also need to be supplied, to authenticate the upload requests.
Private keys should be placed in the `conf/plugins/credentials` folder of the Environet docker project

Run `dareffort plugin create` to start an interactive script, that will guide you through creating an uploader plugin.
Generated configurations will be saved to the `/conf/plugins/configurations` folder, with the filename provided.

5. Running a plugin

Run `dareffort plugin run [configuration name]` to run the uploader plugin. If the upload needs to run regularly, set up a cron job to execute the command at regular intervals.