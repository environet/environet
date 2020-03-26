#!/bin/bash
openssl genpkey -out environet.pem -algorithm rsa -pkeyopt rsa_keygen_bits:2048
openssl rsa -in environet.pem -outform PEM -pubout -out environet_pub.pem
