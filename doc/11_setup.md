# Setup

## Required software
Docker engine - [https://docs.docker.com/install](https://docs.docker.com/install)  
Docker compose - [https://docs.docker.com/compose](https://docs.docker.com/compose)  

## Source code

1. Clone the two git repositories needed to run the app:

   * The Environet docker environment: [https://github.com/environet/environet-docker](https://github.com/environet/environet-docker)
   * The Environet source code [https://github.com/environet/environet](https://github.com/environet/environet)

2. Configure the environment

   In the *environet-docker* folder, create a new .env file by copying .env.example.

   - If the two repositories are not in the same folder, change the SRC_ROOT variable to the path of the *environet* repository (relative or absolute).
