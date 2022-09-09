# Setup

This guide assumes you are installing Environet in a Linux environment.

## Install the Docker Engine and Docker Compose


### Docker Engine
The docker documentation has lots of helpful information, along with distribution specific installation instructions. If you are new to Docker, we recommend reading the [overview](https://docs.docker.com/get-started/) first.
To see distribution specific installation instructions, navigate to Docker Engine > Install > Server > Your platform in the [documentation](https://docs.docker.com/engine/install/).

Be sure to follow the [post installation steps](https://docs.docker.com/engine/install/linux-postinstall/) to allow using docker as a non-root user.

You can verify that Docker is installed correctly by running:  
`$ docker run hello-world`  
If should output a friendly message to the terminal.

### Docker Compose [Deprecated]
In case of new installations (after June of 2021) Compose V2 is integrated in docker or docker-desktop. For this kind of installations it's not necessary to install docker-compose separately. 
In case of Compose V2 every `docker-compose` command in this documentation must be understood as `docker compose` (without hyphen). The `environet` entrypoint script is compatible with both versions.

If it is still necessary to install `docker-compose` separately:

* *Setting up Compose is a simpler process, which is described in detail on [this page](https://docs.docker.com/compose/install/#install-compose-on-linux-systems).  
It involves downloading the docker-compose binary from github and setting executable permissions on it.*

* *You can verify that Docker Compose is installed correctly by running:*  
`$ docker-compose --version`  
*It should output the currently installed version number.*

## Get the source

You will need to have Git installed to be able to download the project source, and to receive updates later. It is easiest to install Git on Linux using the preferred package manager of your Linux distribution. See the [Git downloads page](https://git-scm.com/download/linux) for details.

Checkout the Environet docker repository
  - Navigate to the directory where you would like to install environet  
  - Run `$ git clone https://github.com/environet/environet-docker.git --recurse-submodules`  
    
   By default, the files will be downloaded to a directory named `environet-docker`, you can specify a custom name by providing a third argument to the command, e.g.:  
   `$ git clone https://github.com/environet/environet-docker.git my_directory --recurse-submodules`
   
   Note: If you cloned the repository without the `--recurse-submodules` flag, you need to run `git submodule init` and `git submodule update`, to get the src files checked out.

Change to the directory you checked the code out to, and you should be ready to proceed with the setup.  

  If you are installing a data node, refer to the data node [setup instructions](30_data_node.md)  

  If you are installing a distribution node, refer to the distribution node [setup instructions](21_setup.md)
  
## Getting updates and maintenance

The `environet` cli script is a wrapper for some docker containers managed with docker compose. After first starting a *dist* or *data* node, these services will start automatically after a system reboot.  
To stop and start them manually, you may run `./environet data up` or `./environet data down` (`./environet dist up` and `./environet dist down` in case of a distribution node).  

To get the latest version, simply run `git pull` in the repository folder.  

Depending on the git version it can be possible to run the following command to update the submodule (src folder) too:
`git submodule update --init --recursive --remote`

If a Dockerfile of a container has been changed in the new version, after `./environet dist/data down` but before `./environet dist/data up` it's necessary to run `./environet dist/data build`.

## Linux with UFW security settings

If the nodes are hosted on a linux system, which using UFW firewall, there are some additional steps to make it secure, and do not open unneccessary ports.
The recommended solution is to make some modification on UFW rules. The description of the compatibility problem, and the solution can be found here: [https://github.com/chaifeng/ufw-docker](https://github.com/chaifeng/ufw-docker)

## Installation on Windows

For windows you'll need "Docker Desktop on Windows". This will install docker engine and dashboard. 

Before pulling the source it is necessary to turn of git's `autocrlf` feature, to keep files line endings in UNIX-style. You can do this with this command:
`git config --global core.autocrlf false`

If `bash` is not installed on your computer, you should use `environet.bat` instead of `environet` for all commands. The arguments and the parameters are the same.