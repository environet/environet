# Other Environet tools

Useful CLI tools which are available through the './environet' wrapper script.

### Generating keypair

Command: `./environet dist|data tool keygen`

Description:
With this command you gen generate a openssl keypair with an interactive process. The command will ask for:
* Destination folder of the key files
* Prefix for the key file names

Based on these data a keypair will be generated, and stored in the destination folder.

### Sign content

Command: `./environet dist|data tool sign`

Description:
With this command you gen sign a content (a string, or a file) with a private key. The command will ask for:
* Relative path of private key file
* How do you want to enter the content (file, or pasted string)
* The file path (in case of 'file' mode) or the raw string (in case of 'pasted string' mode)
* Generate md5 hash before sigining, or not

Based on the input data a base64 encoded signature will be generated, which can be used in the Authorization header of any api request.

