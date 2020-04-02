# Data node

## Overview
A *data node* is a configuration mode of Environet, which designed to be run at a data source with the purpose of gathering metering point measurements stored in some third party format, such as a plain text file, spreadsheet or web resource.  
It transforms these data to a format compatible with the Environet *distribution node* API, and uploads the results to a distribution node on behalf of an API user.  
The gathering and uploading of data is accomplished by way of uploader plugin configurations, which can then be run by calling a script, typically by a cron job.


## Setup

1. Install the environet project. Refer to [Setup](11_setup.md)

2. Configure data directory
   - If your data node will be reading data from a file or directory, configure the LOCAL_DATA_DIR variable with the path to the directory where the data will be found.

3. Start the data node container

   `./environet data up`

4. Creating uploader plugin configurations

   ##### Prerequisites

   For an uploader configuration to work, the metering points and observable properties (for which the plugin will upload measurements) have to be configured beforehand on the Environet distribution node that the plugin will be uploading to.  
   
   An API user with upload permissions and an SSL key will also need to be configured, to authenticate upload requests.  
To generate an ssl key pair, you can run the command `./environet data tool keygen` 
Private keys should be placed in the `conf/plugins/credentials` directory, which is where the keygen tool will place them, by default.
    
   ##### Plugin configuration command line tool
   Run `./environet data plugin create` to start an interactive script, that will guide you through creating an uploader plugin configuration.  
    
   Generated configurations will be saved to the `/conf/plugins/configurations` folder, with the filename provided.

5. Running a configuration

   Run `./environet plugin run [configuration name]` to run an uploader plugin configuration. (If the upload needs to run regularly, you would have to set up a cron job to execute the command at regular intervals.)
