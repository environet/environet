# Setup

This guide assumes you are installing Environet in a Linux environment.

## Install the Docker Engine and Docker Compose


### Docker Engine
The docker documentation has lots of helpful information, along with distribution specific installation instructions. If you are new to Docker, we recommend reading the [overview](https://docs.docker.com/install/) first.  
To see distribution specific installation instructions, navigate to Docker Engine > Linux > Your distribution in the [documentation](https://docs.docker.com/install/).

Be sure to follow the [post installation steps](https://docs.docker.com/install/linux/linux-postinstall/) to allow using docker as a non-root user.

You can verify that Docker is installed correctly by running:  
`$ docker run hello-world`  
If should output a friendly message to the terminal.

### Docker Compose
Setting up Compose is a simpler process, which is described in detail on [this page](https://docs.docker.com/compose/install/#install-compose-on-linux-systems).  
It involves downloading the docker-compose binary from github and setting executable permissions on it.

You can verify that Docker Compose is installed correctly by running:  
`$ docker-compose --version`  
It should output the currently installed version number.

## Get the source

You will need to have Git installed to be able to download the project source, and to receive updates later. It is easiest to install Git on Linux using the preferred package manager of your Linux distribution. See the [Git downloads page](https://git-scm.com/download/linux) for details.

Checkout the Environet docker repository
  - Navigate to the directory where you would like to install environet  
  - Run `$ git clone git@github.com:environet/environet-docker.git`  
    
   By default, the files will be downloaded to a directory named `environet-docker`, you can specify a custom name by providing a third argument to the command, e.g.:  
   `$ git clone git@github.com:environet/environet-docker.git my_directory`

Change to the directory you checked the code out to, and you should be ready to proceed with the setup.  

  If you are installing a data node, refer to the data node [setup instructions](30_data_node.md)  

  If you are installing a distribution node, refer to the distribution node [setup instructions](21_setup.md)
  
## Getting updates and maintenance

The `environet` cli script is a wrapper for some docker containers managed with docker compose. After first starting a *dist* or *data* node, these services will start automatically after a system reboot.  
To stop and start them manually, you may run `./environet data up` or `./environet data down` (`./environet dist up` and `./environet dist down` in case of a distribution node).  

To get the latest version, simply run `git pull` in the repository folder.