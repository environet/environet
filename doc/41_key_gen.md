# Private and Public key generation

First of all you have to download the openssl library.

## In case of windows
It is recommended to use the following:

https://itefix.net/dl/free-software/openssl_tool_win_x64_1.0.0.zip

Download and extract it to an optional place. In the package, you can find the executable file at the: 
*bin/openssl.exe*

If you open the exe, it will prompt a command window, wherein you have to type the following lines:

**1.** Private key generation:
>   *genrsa -out private.pem 2048*
  
**2.** Public key generation from private key:
>   *rsa -in private.pem -out public.pem -outform PEM -pubout*

After that you feel free to send your public key to anywhere.

## In case of linux

You have to download one from the following link:

https://www.openssl.org/source/

After that you have to extract and install it.

Here you can find a detailed description about the installation:

https://www.tecmint.com/install-openssl-from-source-in-centos-ubuntu/

After the installation you have to run these commands:

**1.** Private key generation:
>   *openssl genrsa -out private.pem 2048*
  
**2.** Public key generation from private key:
>   *openssl rsa -in private.pem -out public.pem -outform PEM -pubout*

After that you feel free to send your public key to anywhere.
