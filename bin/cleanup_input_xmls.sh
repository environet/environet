#!/usr/bin/env bash

SCRIPT_DIR="$( cd "$( dirname "$0" )" >/dev/null 2>&1 && pwd )"
INPUT_XMLS_DIR="$(dirname "$SCRIPT_DIR")/data/input_xmls"

cd "$INPUT_XMLS_DIR" || exit

declare -a DAYS
declare -a FILES

readarray -t DAYS < <(find "$INPUT_XMLS_DIR" -type f -name "*.xml" -printf "%TY%Tm%Td\n" | sort | uniq)
readarray -t FILES < <(find "$INPUT_XMLS_DIR" -type f -name "*.xml" -printf "%P %TY%Tm%Td\n")

for i in "${DAYS[@]}"; do
  FILESTOCOMPRESS=()
  for j in "${FILES[@]}"; do
    FILEDATA=($j)
    if [[ "${FILEDATA[1]}" == "$i" ]]; then
      FILESTOCOMPRESS+=("${FILEDATA[0]}")
    fi
  done

  if [[ ${#FILESTOCOMPRESS[@]} -ne 0 ]]; then
     tar -czf "$i".tar.gz "${FILESTOCOMPRESS[@]}"
     rm "${FILESTOCOMPRESS[@]}"
  fi
done