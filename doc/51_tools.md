# Other Environet tools

Useful CLI tools which are available through the './environet' wrapper script.

### Data node cleanup

Command: `./environet data cleanup`

Description:
Cleanup data node, delete unnecessary, old files.

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

### Exporting database

Command: `./environet dist database export`

Description:
This commands exports the database content of the distribution node to a file under data/export folder. The filename of the exported file will be written to the console after successful export.

### Generating HTML documentation and merged markdown

Command: `./environet dist generate-merged-html`

Description:
During development, when markdown documentation has been changed, it's necessary to generate updated HTML documentation and merged markdown file.
It's possible with this command.

